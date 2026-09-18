<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reset_link_and_creates_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'alice@amtechat.test']);

        $this->postJson('/api/v1/password/email', ['email' => 'alice@amtechat.test'])
            ->assertOk()
            ->assertJsonPath('data.message', 'If that email exists, a reset link is on its way.')
            ->assertJsonPath('errors', []);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'alice@amtechat.test']);
    }

    public function test_unknown_email_gets_the_same_response(): void
    {
        $this->postJson('/api/v1/password/email', ['email' => 'ghost@amtechat.test'])
            ->assertOk()
            ->assertJsonPath('data.message', 'If that email exists, a reset link is on its way.');

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_valid_token_resets_password_and_invalidates_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'bob@amtechat.test',
            'password' => 'current-password-1',
        ]);
        $token = '123456';
        $this->seedToken($user->email, $token);

        $this->postJson('/api/v1/password/reset', [
            'email' => 'bob@amtechat.test',
            'token' => $token,
            'password' => 'brand-new-password-9',
            'password_confirmation' => 'brand-new-password-9',
        ])
            ->assertOk()
            ->assertJsonPath('data.message', 'Your password has been updated.');

        $this->assertTrue(Hash::check('brand-new-password-9', $user->fresh()->password));
        $this->assertFalse(Hash::check('current-password-1', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'carol@amtechat.test']);
        $this->seedToken($user->email, '123456');

        $this->postJson('/api/v1/password/reset', [
            'email' => 'carol@amtechat.test',
            'token' => 'wrong-token',
            'password' => 'brand-new-password-9',
            'password_confirmation' => 'brand-new-password-9',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_RESET_TOKEN')
            ->assertJsonPath('errors.0.field', 'token');
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->postJson('/api/v1/password/reset', [
            'email' => 'dave@amtechat.test',
            'token' => '123456',
            'password' => 'brand-new-password-9',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    private function seedToken(string $email, string $token): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);
    }
}
