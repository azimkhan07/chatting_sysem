<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Social\Models\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AccountCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_overview_reports_the_sign_in_details(): void
    {
        $user = User::factory()->create(['email' => 'me@test.dev', 'mobile' => '9876500011']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/account')
            ->assertOk()
            ->assertJsonPath('data.account.username', $user->username)
            ->assertJsonPath('data.account.email', 'me@test.dev')
            ->assertJsonPath('data.account.mobile', '9876500011')
            ->assertJsonPath('data.account.status', 'active')
            ->assertJsonPath('data.account.is_deactivated', false)
            ->assertJsonStructure(['data' => ['account' => ['counts' => ['posts', 'followers', 'following']]]]);
    }

    public function test_the_account_center_requires_a_signed_in_user(): void
    {
        $this->getJson('/api/v1/me/account')->assertUnauthorized();
        $this->getJson('/api/v1/me/sessions')->assertUnauthorized();
        $this->postJson('/api/v1/me/account/password', [])->assertUnauthorized();
    }

    public function test_the_password_can_be_changed_and_other_sessions_are_dropped(): void
    {
        $user = $this->user();
        $otherToken = $user->createToken('web · other', ['*']);

        $response = $this->withToken($this->signIn($user))
            ->postJson('/api/v1/me/account/password', [
                'current_password' => 'password',
                'new_password' => 'brand-new-secret-1',
                'new_password_confirmation' => 'brand-new-secret-1',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.password_changed', true)
            ->assertJsonPath('data.other_sessions_revoked', 1);

        $this->assertTrue(Hash::check('brand-new-secret-1', $user->refresh()->password));
        $this->assertNull(PersonalAccessToken::query()->find($otherToken->accessToken->id));
        // The session that made the change keeps working.
        $this->assertNotNull(PersonalAccessToken::query()->find($this->tokenId($user)));
    }

    public function test_a_password_change_needs_the_current_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/api/v1/me/account/password', [
                'current_password' => 'not-my-password',
                'new_password' => 'brand-new-secret-1',
                'new_password_confirmation' => 'brand-new-secret-1',
            ])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'INVALID_CREDENTIALS');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_a_password_change_cannot_reuse_the_current_password(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/me/account/password', [
                'current_password' => 'password',
                'new_password' => 'password',
                'new_password_confirmation' => 'password',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'new_password');
    }

    public function test_a_weak_new_password_is_refused(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/me/account/password', [
                'current_password' => 'password',
                'new_password' => 'short',
                'new_password_confirmation' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_sessions_are_listed_with_the_current_one_marked(): void
    {
        $user = $this->user();
        $user->createToken('web · phone', ['*']);

        $response = $this->withToken($this->signIn($user))->getJson('/api/v1/me/sessions');
        $response->assertOk();

        $sessions = $response->json('data.sessions');
        $this->assertCount(2, $sessions);
        $this->assertCount(1, array_filter($sessions, fn (array $s): bool => $s['is_current'] === true));
        // The token value itself is never handed back.
        $this->assertStringNotContainsString('plain', json_encode($response->json(), JSON_THROW_ON_ERROR));
    }

    public function test_another_session_can_be_revoked(): void
    {
        $user = $this->user();
        $other = $user->createToken('web · phone', ['*']);

        $this->withToken($this->signIn($user))
            ->deleteJson("/api/v1/me/sessions/{$other->accessToken->id}")
            ->assertOk()
            ->assertJsonPath('data.revoked', true);

        $this->assertNull(PersonalAccessToken::query()->find($other->accessToken->id));
    }

    public function test_the_session_in_use_cannot_be_revoked_from_the_sessions_list(): void
    {
        $user = $this->user();
        $this->signIn($user);

        $this->withToken($this->plainToken)
            ->deleteJson("/api/v1/me/sessions/{$this->tokenId($user)}")
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CURRENT_SESSION');
    }

    public function test_revoking_an_unknown_session_is_a_404(): void
    {
        $this->actingAs($this->user())
            ->deleteJson('/api/v1/me/sessions/9999')
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'NOT_FOUND');
    }

    public function test_signing_out_everywhere_else_keeps_the_current_session(): void
    {
        $user = $this->user();
        $user->createToken('web · phone', ['*']);
        $user->createToken('web · tablet', ['*']);

        $this->withToken($this->signIn($user))
            ->deleteJson('/api/v1/me/sessions')
            ->assertOk()
            ->assertJsonPath('data.revoked', true)
            ->assertJsonPath('data.other_sessions_revoked', 2);

        $only = PersonalAccessToken::query()->get();
        $this->assertCount(1, $only);
        $this->assertSame($this->tokenId($user), $only->first()->id);
    }

    public function test_a_deactivated_account_cannot_sign_in_and_says_so(): void
    {
        $user = $this->user(['username' => 'sleeper']);

        $this->actingAs($user)
            ->postJson('/api/v1/me/account/deactivate', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.deactivated', true);

        $this->assertNotNull($user->refresh()->deactivated_at);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'sleeper',
            'password' => 'password',
        ])
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'ACCOUNT_DEACTIVATED');
    }

    public function test_deactivation_revokes_every_token(): void
    {
        $user = $this->user();
        $user->createToken('web · phone', ['*']);

        $this->actingAs($user)->postJson('/api/v1/me/account/deactivate', ['password' => 'password']);

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_deactivation_needs_the_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/api/v1/me/account/deactivate', ['password' => 'wrong'])
            ->assertStatus(401);

        $this->assertNull($user->refresh()->deactivated_at);
    }

    public function test_a_suspended_account_is_still_reported_as_suspended_not_deactivated(): void
    {
        $user = $this->user(['status' => UserStatus::Suspended->value]);

        // A suspension is an admin decision and out of scope for self-service,
        // so the account is off limits entirely.
        $this->actingAs($user)->getJson('/api/v1/me/account')->assertForbidden();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->username,
            'password' => 'password',
        ])->assertStatus(403)->assertJsonPath('errors.0.code', 'ACCOUNT_DISABLED');
    }

    public function test_a_deactivated_account_can_be_reactivated_with_its_password(): void
    {
        $user = $this->user(['username' => 'sleeper']);
        $this->actingAs($user)
            ->postJson('/api/v1/me/account/deactivate', ['password' => 'password'])
            ->assertOk();

        $this->postJson('/api/v1/auth/reactivate', [
            'identifier' => 'sleeper',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.reactivated', true)
            ->assertJsonPath('data.user.username', 'sleeper')
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'user']]);

        $this->assertNull($user->refresh()->deactivated_at);
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_reactivation_with_the_wrong_password_changes_nothing(): void
    {
        $user = $this->user(['username' => 'sleeper']);
        $this->actingAs($user)->postJson('/api/v1/me/account/deactivate', ['password' => 'password']);

        $this->postJson('/api/v1/auth/reactivate', [
            'identifier' => 'sleeper',
            'password' => 'nope',
        ])->assertStatus(401);

        $this->assertNotNull($user->refresh()->deactivated_at);
    }

    public function test_reactivation_is_not_a_second_login_endpoint(): void
    {
        $this->postJson('/api/v1/auth/reactivate', [
            'identifier' => $this->user()->username,
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_the_export_contains_the_users_data_and_no_credentials(): void
    {
        $user = $this->user(['username' => 'exporter', 'email' => 'exporter@test.dev']);
        $friend = $this->user();
        Follow::query()->create([
            'follower_id' => $user->id,
            'following_id' => $friend->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/me/account/export');
        $response->assertOk()->assertHeader('content-disposition', 'attachment; filename="amtechat-export-exporter.json"');

        $body = $response->json();
        $this->assertSame('exporter', $body['account']['username']);
        $this->assertSame('exporter@test.dev', $body['account']['email']);
        $this->assertSame(1, $body['counts']['following']);
        $this->assertSame([$friend->username], array_column($body['connections']['following'], 'username'));
        $this->assertArrayHasKey('privacy', $body['settings']);

        // Credentials must never travel in an export file.
        $encoded = json_encode($body, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(Hash::make('x'), $encoded);
        $this->assertStringNotContainsString('$2y$', $encoded);
        $this->assertArrayNotHasKey('password', $body['account']);
    }

    public function test_deleting_the_account_anonymises_it_and_breaks_the_login(): void
    {
        $user = $this->user(['username' => 'leaver', 'email' => 'leaver@test.dev']);
        $friend = $this->user();
        Follow::query()->create([
            'follower_id' => $user->id,
            'following_id' => $friend->id,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/me/account', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $row = User::query()->withTrashed()->whereKey($user->id)->firstOrFail();
        $this->assertNotSame('leaver', $row->username);
        $this->assertNull($row->email);
        $this->assertSame('Deleted user', $row->display_name);
        $this->assertFalse($row->is_verified);
        $this->assertTrue($row->trashed());

        // Both sides of the follow edge are gone, so nobody keeps a ghost
        // follower.
        $this->assertSame(0, Follow::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'leaver',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_deleting_the_account_needs_the_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->deleteJson('/api/v1/me/account', ['password' => 'wrong'])
            ->assertStatus(401);

        $this->assertFalse($user->refresh()->trashed());
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private string $plainToken = '';

    /**
     * Issues a real Sanctum token and keeps its value for the assertions.
     *
     * `Sanctum::actingAs()` installs a `TransientToken` with no id, which is
     * fine for "is this user allowed" but useless for anything that has to
     * reason about the current session.
     */
    private function signIn(User $user): string
    {
        $this->plainToken = $user->createToken('web · test', ['*'])->plainTextToken;

        return $this->plainToken;
    }

    private function tokenId(User $user): int
    {
        /** @var PersonalAccessToken $token */
        $token = PersonalAccessToken::query()
            ->where('token', hash('sha256', explode('|', $this->plainToken, 2)[1]))
            ->firstOrFail();

        return (int) $token->id;
    }
}
