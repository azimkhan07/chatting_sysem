<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Models\StaffUser;
use App\Domain\Admin\Models\SupportStaff;
use App\Domain\Moderation\Models\AccountAppeal;
use App\Domain\Moderation\Services\AppealService;
use App\Domain\Support\Models\SupportMessage;
use App\Domain\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyTicketRequest;
use App\Http\Requests\ResolveAppealRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support desk. Tickets are user-filed queries/complaints; the support role
 * replies inline (and optionally emails the user through the configured
 * transport - the email lib is plugged in here once the mail config exists).
 *
 * The support role also owns two queues:
 *   - support agents (the accounts that staff the desk), and
 *   - suspension appeals (a suspended user's request to be unsuspended), which
 *     are approved/rejected straight from this screen.
 */
final class SupportAdminController extends Controller
{
    public function __construct(private readonly AppealService $appeals) {}

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', '');
        $page = max(1, (int) $request->integer('page', 1));
        $limit = (int) $request->integer('limit', 25);

        $query = SupportTicket::query()->with(['user', 'messages' => fn ($q) => $q->latest()]);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::success([
            'tickets' => collect($paginator->items())->map(static fn (SupportTicket $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'message' => $t->message,
                'status' => $t->status,
                'priority' => $t->priority,
                'user_name' => $t->user?->display_name,
                'username' => $t->user?->username,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    public function reply(ReplyTicketRequest $request, int $ticketId): JsonResponse
    {
        $ticket = SupportTicket::query()->find($ticketId);

        if ($ticket === null) {
            return ApiResponse::error('NOT_FOUND', 'Ticket not found.', 404);
        }

        $ticket->messages()->create([
            'staff_user_id' => $request->user()->id,
            'from_support' => true,
            'body' => $request->validated('reply'),
            'emailed' => (bool) $request->validated('email', false),
        ]);

        $ticket->update(['status' => 'replied']);

        // TODO(email): send through the configured transport + EmailTemplate
        //              when a gateway/template is chosen.

        return ApiResponse::success([
            'ok' => true,
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
            ],
        ]);
    }

    /**
     * The accounts staffing the support desk. Regular end-users are excluded;
     * every listed agent is a console account whose support profile row marks
     * them as desk staff.
     */
    public function agents(Request $request): JsonResponse
    {
        $roleNames = [StaffUser::ROLE_SUPPORT, StaffUser::ROLE_ADMIN, StaffUser::ROLE_SUPER_ADMIN];

        $agents = StaffUser::query()
            ->whereIn('role', $roleNames)
            ->whereHas('supportProfile')
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (StaffUser $u): array => [
                'id' => (int) $u->id,
                'username' => $u->username,
                'display_name' => $u->display_name,
                'role' => $u->role,
                'status' => 'active',
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success(['agents' => $agents->values()]);
    }

    /**
     * Suspension appeals, pending first (the queue the support role works fast).
     */
    public function appeals(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'pending');
        $allowed = [AccountAppeal::STATUS_PENDING, AccountAppeal::STATUS_APPROVED, AccountAppeal::STATUS_REJECTED];

        $appeals = AccountAppeal::query()
            ->with(['user', 'handler'])
            ->when($status !== 'all' && in_array($status, $allowed, true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(100)
            ->get()
            ->map(static fn (AccountAppeal $a): array => [
                'id' => $a->id,
                'status' => $a->status,
                'message' => $a->message,
                'resolution' => $a->resolution,
                'handled_at' => $a->handled_at?->toIso8601String(),
                'created_at' => $a->created_at?->toIso8601String(),
                'user' => [
                    'id' => $a->user?->id,
                    'username' => $a->user?->username,
                    'display_name' => $a->user?->display_name,
                    'status' => $a->user?->status->value,
                ],
                'handler' => [
                    'id' => $a->handler?->id,
                    'username' => $a->handler?->username,
                    'display_name' => $a->handler?->display_name,
                ],
            ]);

        return ApiResponse::success(['appeals' => $appeals->values()]);
    }

    public function resolveAppeal(ResolveAppealRequest $request, int $appealId): JsonResponse
    {
        $appeal = AccountAppeal::query()->with('user')->find($appealId);

        if ($appeal === null) {
            return ApiResponse::error('NOT_FOUND', 'Appeal not found.', 404);
        }

        if (! $appeal->isPending()) {
            return ApiResponse::error('ALREADY_HANDLED', 'This appeal has already been handled.', 409);
        }

        $action = (string) $request->validated('action');

        $appeal = $action === 'approve'
            ? $this->appeals->approve((int) $request->user()->id, $appeal, $request->validated('resolution'))
            : $this->appeals->reject((int) $request->user()->id, $appeal, $request->validated('resolution'));

        return ApiResponse::success([
            'appeal' => [
                'id' => $appeal->id,
                'status' => $appeal->status,
            ],
        ]);
    }
}