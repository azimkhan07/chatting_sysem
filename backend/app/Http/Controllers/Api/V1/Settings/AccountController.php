<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domain\Auth\Models\User;
use App\Domain\Settings\Services\AccountService;
use App\Domain\Settings\Services\DataExportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ConfirmAccountActionRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $account,
        private readonly DataExportService $export,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(['account' => $this->account->overview($user)]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $revoked = $this->account->changePassword(
            $user,
            (string) $request->validated('current_password'),
            (string) $request->validated('new_password'),
        );

        return ApiResponse::success([
            'password_changed' => true,
            'other_sessions_revoked' => $revoked,
        ]);
    }

    public function deactivate(ConfirmAccountActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->account->deactivate($user, (string) $request->validated('password'));

        return ApiResponse::success(['deactivated' => true]);
    }

    public function destroy(ConfirmAccountActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->account->delete($user, (string) $request->validated('password'));

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * A copy of everything we hold about the signed-in user.
     *
     * Sent as a download rather than a paginated resource: the point is a single
     * portable file, and a user should not have to page through it.
     */
    public function export(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filename = 'amtechat-export-'.$user->username.'.json';

        return response()->json($this->export->for($user))
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('X-Export-Version', '1');
    }
}
