<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Services;

use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Models\User;
use App\Domain\Moderation\Models\AccountAppeal;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The suspension appeal lifecycle.
 *
 * File: a suspended user proves who they are (identifier + password, the same
 * proof account reactivation uses) and leaves a explainer. No token exists -
 * suspension revoked them - so this cannot be an authenticated route.
 *
 * Handle: the support desk either approves (user flips back to `active`,
 * stale tokens die so old sessions cannot resume) or rejects with a reason.
 */
final class AppealService
{
    public function __construct(private readonly AuthRepository $users) {}

    public function file(string $identifier, string $password, string $message): AccountAppeal
    {
        $user = $this->users->findByIdentifier($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        if ($user->status !== UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'identifier' => 'Only suspended accounts can be appealed. This account is not suspended.',
            ]);
        }

        $pending = AccountAppeal::query()
            ->where('user_id', $user->id)
            ->where('status', AccountAppeal::STATUS_PENDING)
            ->first();

        if ($pending !== null) {
            throw ValidationException::withMessages([
                'message' => 'You already have an appeal under review. The support team is on it.',
            ]);
        }

        return AccountAppeal::query()->create([
            'user_id' => $user->id,
            'message' => trim($message),
            'status' => AccountAppeal::STATUS_PENDING,
        ]);
    }

    /**
     * Approve: lift the suspension and revoke every stale session.
     */
    public function approve(int $handlerId, AccountAppeal $appeal, ?string $resolution): AccountAppeal
    {
        $user = $appeal->user;

        $appeal->forceFill([
            'status' => AccountAppeal::STATUS_APPROVED,
            'handled_by' => $handlerId,
            'resolution' => $resolution !== null && trim($resolution) !== '' ? trim($resolution) : null,
            'handled_at' => now(),
        ])->save();

        if ($user !== null) {
            $user->forceFill(['status' => UserStatus::Active->value])->save();
            $user->tokens()->delete();
        }

        return $appeal->refresh();
    }

    /**
     * Reject: the suspension stands.
     */
    public function reject(int $handlerId, AccountAppeal $appeal, ?string $resolution): AccountAppeal
    {
        $appeal->forceFill([
            'status' => AccountAppeal::STATUS_REJECTED,
            'handled_by' => $handlerId,
            'resolution' => $resolution !== null && trim($resolution) !== '' ? trim($resolution) : null,
            'handled_at' => now(),
        ])->save();

        return $appeal->refresh();
    }
}