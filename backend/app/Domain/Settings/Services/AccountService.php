<?php

declare(strict_types=1);

namespace App\Domain\Settings\Services;

use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Models\User;
use App\Domain\Social\Models\Follow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Account Center: everything a user can do to their own sign-in.
 *
 * The destructive paths (deactivate, delete) all require a fresh password
 * check, because a hijacked token that can also wipe the account is the worst
 * possible combination. A stale bearer token is not enough.
 */
final class AccountService
{
    public function __construct(private readonly AuthRepository $users) {}

    /**
     * Change the password and drop every *other* session.
     *
     * The caller keeps working: forcing a logout on a password change means a
     * user who suspects theft cannot tell whether the thief was kicked.
     *
     * @return int the number of other sessions that were revoked
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): int
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new InvalidCredentialsException;
        }

        if (Hash::check($newPassword, $user->password)) {
            // Reusing the current password would silently revoke every other
            // session for no security gain, so refuse it.
            throw ValidationException::withMessages([
                'new_password' => 'Choose a password you have not used here.',
            ]);
        }

        $user->forceFill(['password' => $newPassword])->save();

        return $this->revokeOtherSessions($user);
    }

    /**
     * Put the account to sleep without losing it.
     */
    public function deactivate(User $user, string $password): void
    {
        $this->assertPassword($user, $password);

        $user->forceFill(['deactivated_at' => now()])->save();
        $user->tokens()->delete();
    }

    /**
     * Wake a sleeping account back up. The password is re-checked so that
     * knowing a username is not enough to bring an account back.
     */
    public function reactivate(string $identifier, string $password): User
    {
        // Identifier resolution is reused rather than reimplemented, so
        // username/email/mobile precedence stays identical to sign-in.
        $user = $this->users->findByIdentifier($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        // Checked before the write, not inferred from it: reactivation is not a
        // second, oddly-shaped login endpoint, and an account that is already
        // awake has nothing to reactivate.
        if (! $user->isDeactivated()) {
            throw ValidationException::withMessages([
                'identifier' => 'This account is not deactivated.',
            ]);
        }

        $user->forceFill(['deactivated_at' => null])->save();

        return $user;
    }

    /**
     * Erase the account.
     *
     * Rows other tables point at (posts, messages) are kept so nothing in
     * someone else's timeline breaks, but every personally identifying column is
     * overwritten and the row is soft-deleted, which is what makes the account
     * unloginable and unlinkable. This is a privacy delete, not a
     * referential-integrity delete.
     */
    public function delete(User $user, string $password): void
    {
        $this->assertPassword($user, $password);

        $anonymised = 'deleted_'.Str::lower(Str::random(12));

        DB::transaction(function () use ($user, $anonymised): void {
            $user->tokens()->delete();
            // Follow rows are edges, not user rows, so they are cleared by hand
            // on both sides - otherwise the anonymised account would still be
            // counted in somebody else's follower list.
            Follow::query()
                ->where('follower_id', $user->id)
                ->orWhere('following_id', $user->id)
                ->delete();

            $user->forceFill([
                'username' => $anonymised,
                'display_name' => 'Deleted user',
                'bio' => null,
                'email' => null,
                'mobile' => null,
                'avatar_path' => null,
                'cover_path' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'last_seen_at' => null,
                'account_type' => 'personal',
                'contact_email' => null,
                'contact_phone' => null,
                'show_contact' => false,
                'is_verified' => false,
                'deactivated_at' => now(),
            ])->save();

            $user->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        return [
            'username' => $user->username,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'display_name' => $user->display_name,
            'account_type' => $user->account_type->value,
            'status' => $user->status->value,
            'is_verified' => $user->is_verified,
            'is_deactivated' => $user->isDeactivated(),
            'deactivated_at' => $user->deactivated_at?->toIso8601String(),
            'member_since' => $user->created_at->toIso8601String(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'counts' => [
                'posts' => $user->posts()->count(),
                'followers' => $user->followers()->count(),
                'following' => $user->following()->count(),
            ],
        ];
    }

    private function assertPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException;
        }
    }

    /**
     * Revoke every token except the one making the request.
     */
    private function revokeOtherSessions(User $user): int
    {
        $currentId = $user->currentTokenId();

        return (int) $user->tokens()
            ->when($currentId !== null, fn ($query) => $query->whereKeyNot($currentId))
            ->delete();
    }
}
