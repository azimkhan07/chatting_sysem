<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_PAYLOAD = [
        'username' => 'ayaankhan',
        'display_name' => 'Ayaan Khan',
        'email' => 'ayaan@example.com',
        'mobile' => '9876543210',
        'password' => 'secretpass123',
        'password_confirmation' => 'secretpass123',
    ];

    public function test_user_can_register_and_receives_access_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', self::REGISTER_PAYLOAD);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'username']],
            ])
            ->assertJsonPath('data.user.username', 'ayaankhan')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('errors', []);

        $this->assertDatabaseHas('users', [
            'username' => 'ayaankhan',
            'email' => 'ayaan@example.com',
            'mobile' => '9876543210',
        ]);
    }

    public function test_username_cannot_be_duplicated(): void
    {
        User::factory()->create(['username' => 'ayaankhan']);

        $this->postJson('/api/v1/auth/register', self::REGISTER_PAYLOAD)
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'USERNAME_TAKEN')
            ->assertJsonPath('errors.0.field', 'username');
    }

    public function test_same_email_can_own_multiple_accounts(): void
    {
        $this->postJson('/api/v1/auth/register', self::REGISTER_PAYLOAD)->assertStatus(201);

        $second = [...self::REGISTER_PAYLOAD, 'username' => 'ayaanfan', 'display_name' => 'Ayaan Fan'];
        $this->postJson('/api/v1/auth/register', $second)->assertStatus(201);

        $this->assertDatabaseCount('users', 2);
        $this->assertSame(2, User::query()->where('email', 'ayaan@example.com')->count());
    }

    public function test_same_mobile_can_own_multiple_accounts(): void
    {
        User::factory()->create(['mobile' => '9876543210']);

        $this->postJson('/api/v1/auth/register', self::REGISTER_PAYLOAD)->assertStatus(201);

        $this->assertSame(
            2,
            User::query()->where('mobile', '9876543210')->count(),
        );
    }

    public function test_username_and_email_are_lowercased_and_trimmed(): void
    {
        $payload = [...self::REGISTER_PAYLOAD, 'username' => '  AyaanPro  ', 'email' => '  AYAAN@EXAMPLE.COM  '];

        $this->postJson('/api/v1/auth/register', $payload)->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'username' => 'ayaanpro',
            'email' => 'ayaan@example.com',
        ]);
    }

    public function test_registration_requires_minimum_password_length(): void
    {
        $payload = [...self::REGISTER_PAYLOAD, 'password' => 'short', 'password_confirmation' => 'short'];

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'password');
    }

    public function test_user_can_login_with_username(): void
    {
        User::factory()->create(['username' => 'ayaankhan', 'password' => 'secretpass123']);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ayaankhan',
            'password' => 'secretpass123',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'user']])
            ->assertJsonPath('data.user.username', 'ayaankhan');
    }

    public function test_user_can_login_with_email(): void
    {
        User::factory()->create(['email' => 'ayaan@example.com', 'password' => 'secretpass123']);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'AYAAN@EXAMPLE.COM',
            'password' => 'secretpass123',
        ])->assertOk();
    }

    public function test_user_can_login_with_mobile(): void
    {
        User::factory()->create(['mobile' => '9876543210', 'password' => 'secretpass123']);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => '9876543210',
            'password' => 'secretpass123',
        ])->assertOk();
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'ayaankhan', 'password' => 'secretpass123']);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ayaankhan',
            'password' => 'wrongpassword',
        ])->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_accepts_password_with_autofill_whitespace(): void
    {
        User::factory()->create(['username' => 'ayaankhan', 'password' => 'secretpass123']);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ayaankhan',
            'password' => '  secretpass123 ',
        ])->assertOk()
            ->assertJsonPath('data.user.username', 'ayaankhan');
    }

    public function test_login_with_unknown_identifier_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'nobody',
            'password' => 'secretpass123',
        ])->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.username', $user->username)
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;
        $tokenId = $user->tokens()->sole()->id;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        // The guard caches users per application instance; drop it so the
        // revoked token is actually re-checked against the database.
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED');
    }

    public function test_contact_details_are_only_visible_to_the_owner(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'mobile' => '9876500000']);
        $viewer = User::factory()->create();
        $token = $viewer->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/users/{$user->username}")
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonMissingPath('data.user.mobile');
    }

    public function test_owner_sees_their_own_contact_details(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'mobile' => '9876500000']);
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'owner@example.com')
            ->assertJsonPath('data.user.mobile', '9876500000');
    }

    public function test_feed_never_exposes_author_contact_details(): void
    {
        $author = User::factory()->create(['email' => 'author@example.com']);
        $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'hello world']);

        $viewer = User::factory()->create();
        $token = $viewer->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('data.posts.0.id', $post->id)
            ->assertJsonMissingPath('data.posts.0.user.email')
            ->assertJsonMissingPath('data.posts.0.user.mobile');
    }

    public function test_suspended_account_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'suspendeduser',
            'password' => 'secretpass123',
            'status' => UserStatus::Suspended,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'suspendeduser',
            'password' => 'secretpass123',
        ])->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'ACCOUNT_DISABLED');
    }

    public function test_suspended_account_token_is_revoked_on_next_request(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Suspended]);
        $token = $user->createToken('web')->plainTextToken;
        $tokenId = $user->tokens()->sole()->id;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'ACCOUNT_DISABLED');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_username_cannot_be_numeric(): void
    {
        $this->postJson('/api/v1/auth/register', [
            ...self::REGISTER_PAYLOAD,
            'username' => '98765',
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'username');
    }

    public function test_numeric_url_segment_resolves_the_account_with_that_id(): void
    {
        $byId = User::factory()->create();
        $shadow = User::factory()->create(['username' => (string) $byId->id]);
        $token = $byId->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/users/{$byId->id}")
            ->assertOk()
            ->assertJsonPath('data.user.username', $byId->username)
            ->assertJsonPath('data.user.id', $byId->id);

        $this->assertNotSame($shadow->id, $byId->id);
    }
}
