<?php

declare(strict_types=1);

namespace Admin\Domain\App\Services;

use Admin\Domain\App\Enums\UserStatus;
use Admin\Domain\App\Models\AccountAppeal;
use Admin\Domain\App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The support desk's half of the suspension appeal lifecycle.
 *
 * Filing an appeal stays in backend/ (it needs password proof from the app's
 * own user record). What lives here is the decision, which is the part staff
 * perform and the part this service exists to own.
 */
final class AppealReviewService
{
    /**
     * The `tokenable_type` the main app writes for its own users.
     *
     * Spelled out as a string rather than `User::class` on purpose. The main
     * app's user model is `App\Domain\Auth\Models\User`, and this app's read
     * model is `Admin\Domain\App\Models\User` — a different class with a
     * different name. Using `User::class` here would match zero rows, and the
     * symptom is not a crash: a reinstated account would silently inherit every
     * session the suspension was supposed to have revoked.
     */
    private const APP_USER_TOKENABLE_TYPE = 'App\Domain\Auth\Models\User';

    /**
     * Approve: lift the suspension and revoke every stale session.
     *
     * The token rows are deleted by hand rather than through a relation: they
     * live in the main app's `personal_access_tokens` table, and the console's
     * User model is a read model that deliberately carries no Sanctum trait.
     */
    public function approve(int $handlerId, AccountAppeal $appeal, ?string $resolution): AccountAppeal
    {
        $appeal->forceFill([
            'status' => AccountAppeal::STATUS_APPROVED,
            'handled_by_staff' => $handlerId,
            'resolution' => $this->normalise($resolution),
            'handled_at' => now(),
        ])->save();

        $this->reinstateUser($appeal->user_id);

        return $appeal->refresh();
    }

    /**
     * Reject: the suspension stands and the account is left alone.
     */
    public function reject(int $handlerId, AccountAppeal $appeal, ?string $resolution): AccountAppeal
    {
        $appeal->forceFill([
            'status' => AccountAppeal::STATUS_REJECTED,
            'handled_by_staff' => $handlerId,
            'resolution' => $this->normalise($resolution),
            'handled_at' => now(),
        ])->save();

        return $appeal->refresh();
    }

    private function reinstateUser(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return;
        }

        $user->forceFill(['status' => UserStatus::Active->value])->save();

        // Suspension revoked these sessions; a reinstated account must not
        // inherit them. Revoking is part of the lift, not a follow-up task.
        // Scoped to this user's rows only: the same table also holds console
        // staff tokens, and an unfiltered delete would sign the support desk
        // out during an appeal decision.
        DB::connection('app')
            ->table('personal_access_tokens')
            ->where('tokenable_type', self::APP_USER_TOKENABLE_TYPE)
            ->where('tokenable_id', $userId)
            ->delete();
    }

    private function normalise(?string $resolution): ?string
    {
        return $resolution !== null && trim($resolution) !== '' ? trim($resolution) : null;
    }
}
