<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Domain\Auth\Enums\AccountType;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_business_account_can_publish_a_whatsapp_contact(): void
    {
        $owner = $this->account();

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Business->value,
            'contact_phone' => '+919876543210',
            'contact_email' => 'hello@studio.test',
            'show_contact' => true,
        ])->assertOk()
            ->assertJsonPath('data.user.account_type', 'business')
            ->assertJsonPath('data.user.is_contact_visible', true);

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'account_type' => 'business',
            'contact_phone' => '+919876543210',
            'show_contact' => true,
        ]);
    }

    public function test_a_published_contact_is_visible_to_a_visitor(): void
    {
        $owner = $this->account();
        $this->makeBusiness($owner);

        Sanctum::actingAs($this->account());

        $this->getJson("/api/v1/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.user.account_type', 'business')
            ->assertJsonPath('data.user.is_contact_visible', true)
            ->assertJsonPath('data.user.contact_phone', '+919876543210')
            ->assertJsonPath('data.user.contact_email', 'hello@studio.test');
    }

    public function test_a_contact_stays_hidden_until_the_owner_turns_it_on(): void
    {
        $owner = $this->account();
        Sanctum::actingAs($owner);

        // Details saved, but not published.
        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Business->value,
            'contact_phone' => '+919876543210',
            'show_contact' => false,
        ])->assertOk()->assertJsonPath('data.user.show_contact', false);

        Sanctum::actingAs($this->account());
        $this->getJson("/api/v1/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.user.is_contact_visible', false)
            ->assertJsonPath('data.user.contact_phone', null)
            ->assertJsonPath('data.user.contact_email', null);
    }

    public function test_a_personal_account_never_offers_contact(): void
    {
        $owner = $this->account();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Professional->value,
            'contact_email' => 'hello@studio.test',
            'show_contact' => true,
        ])->assertOk()->assertJsonPath('data.user.is_contact_visible', true);

        // Downgrading to Personal must wipe the published block, not just hide it.
        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Personal->value,
        ])->assertOk()
            ->assertJsonPath('data.user.account_type', 'personal')
            ->assertJsonPath('data.user.show_contact', false)
            ->assertJsonPath('data.user.contact_email', null);

        Sanctum::actingAs($this->account());
        $this->getJson("/api/v1/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.user.is_contact_visible', false)
            ->assertJsonPath('data.user.contact_phone', null);
    }

    public function test_the_toggle_cannot_publish_an_empty_contact(): void
    {
        $owner = $this->account();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Business->value,
            'show_contact' => true,
        ])->assertOk()->assertJsonPath('data.user.show_contact', false);
    }

    public function test_the_owner_always_sees_their_own_contact_settings(): void
    {
        $owner = $this->account();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Business->value,
            'contact_email' => 'hello@studio.test',
            'show_contact' => false,
        ])->assertOk();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.contact_email', 'hello@studio.test')
            ->assertJsonPath('data.user.show_contact', false);
    }

    public function test_a_visitor_never_sees_the_owner_only_email_or_mobile(): void
    {
        $owner = $this->account();
        $this->makeBusiness($owner);

        Sanctum::actingAs($this->account());

        $response = $this->getJson("/api/v1/users/{$owner->id}")->assertOk();

        $this->assertArrayNotHasKey('email', $response->json('data.user'));
        $this->assertArrayNotHasKey('mobile', $response->json('data.user'));
    }

    public function test_the_contact_block_validates_its_input(): void
    {
        Sanctum::actingAs($this->account());

        $this->patchJson('/api/v1/me', ['account_type' => 'agency'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'account_type');

        $this->patchJson('/api/v1/me', ['contact_phone' => 'call-me-maybe'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'contact_phone');

        $this->patchJson('/api/v1/me', ['contact_email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'contact_email');
    }

    public function test_a_suspended_account_cannot_edit_its_profile(): void
    {
        $owner = $this->account();
        $owner->forceFill(['status' => UserStatus::Suspended->value])->save();

        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/me', ['account_type' => 'business'])
            ->assertForbidden();
    }

    private function makeBusiness(User $owner): void
    {
        $owner->forceFill([
            'account_type' => AccountType::Business->value,
            'contact_phone' => '+919876543210',
            'contact_email' => 'hello@studio.test',
            'show_contact' => true,
        ])->save();
    }

    private function account(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }
}
