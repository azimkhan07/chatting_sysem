<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Support\Models\SupportMessage;
use App\Domain\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyTicketRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support desk. Tickets are user-filed queries/complaints; the support role
 * replies inline (and optionally emails the user through the configured
 * transport - the email lib is plugged in here once the mail config exists).
 */
final class SupportAdminController extends Controller
{
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
            'user_id' => $request->user()->id,
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
}