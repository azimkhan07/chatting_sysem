<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SettingsPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_are_created_with_sensible_defaults_on_first_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/settings')
            ->assertOk()
            ->assertJsonPath('data.settings.privacy.discoverable', true)
            ->assertJsonPath('data.settings.privacy.allow_message_requests', true)
            ->assertJsonPath('data.settings.notifications.notify_messages', true)
            ->assertJsonPath('data.settings.notifications.notify_comments', true);

        $this->assertDatabaseCount('user_settings', 1);
        $this->assertDatabaseHas('user_settings', ['user_id' => $user->id]);
    }

    public function test_preferences_require_authentication(): void
    {
        $this->getJson('/api/v1/me/settings')->assertUnauthorized();
        $this->patchJson('/api/v1/me/settings', ['discoverable' => false])->assertUnauthorized();
    }

    public function test_a_preference_can_be_turned_off(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/settings', [
            'privacy' => ['discoverable' => false, 'show_activity_status' => false],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.privacy.discoverable', false)
            ->assertJsonPath('data.settings.privacy.show_activity_status', false)
            // Untouched keys keep their default.
            ->assertJsonPath('data.settings.privacy.allow_tagging', true);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'discoverable' => false,
            'show_activity_status' => false,
            'allow_tagging' => true,
        ]);
    }

    public function test_preferences_accept_a_flat_body_too(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/settings', ['notify_likes' => false])
            ->assertOk()
            ->assertJsonPath('data.settings.notifications.notify_likes', false);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'notify_likes' => false,
        ]);
    }

    public function test_preferences_survive_a_round_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/settings', [
            'notifications' => ['notify_follows' => false],
            'privacy' => ['allow_tagging' => false],
        ])->assertOk();

        $this->getJson('/api/v1/me/settings')
            ->assertJsonPath('data.settings.notifications.notify_follows', false)
            ->assertJsonPath('data.settings.privacy.allow_tagging', false);
    }

    public function test_a_non_boolean_preference_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/me/settings', ['discoverable' => 'sometimes'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'discoverable');
    }

    public function test_an_unknown_preference_is_rejected_rather_than_written(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/settings', [
            'preferences' => ['is_admin' => true, 'notify_likes' => false],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('user_settings', ['user_id' => $user->id]);
    }

    public function test_a_suspended_account_cannot_read_or_write_preferences(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/settings')->assertForbidden();
        $this->patchJson('/api/v1/me/settings', ['discoverable' => false])->assertForbidden();
    }

    public function test_preferences_are_per_user(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        Sanctum::actingAs($mine);
        $this->patchJson('/api/v1/me/settings', ['discoverable' => false])->assertOk();

        Sanctum::actingAs($theirs);
        // Reading the other account's preferences must create *their* row at the
        // defaults, never return the first user's value.
        $this->getJson('/api/v1/me/settings')
            ->assertOk()
            ->assertJsonPath('data.settings.privacy.discoverable', true);

        $this->assertDatabaseHas('user_settings', ['user_id' => $theirs->id, 'discoverable' => true]);
    }
}
