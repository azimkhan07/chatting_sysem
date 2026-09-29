<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Domain\Auth\Enums\AccountType;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use App\Domain\Social\Models\Follow;
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

    public function test_a_business_account_can_pick_a_creator_category(): void
    {
        $owner = $this->account();

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Business->value,
            'category' => 'artist',
        ])->assertOk()
            ->assertJsonPath('data.user.category', 'artist')
            ->assertJsonPath('data.user.category_label', 'Artist');

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'category' => 'artist',
        ]);
    }

    public function test_a_professional_account_can_carry_a_category_too(): void
    {
        $owner = $this->account();

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Professional->value,
            'category' => 'sport',
        ])->assertOk()
            ->assertJsonPath('data.user.category', 'sport');
    }

    public function test_a_personal_account_never_carries_a_category(): void
    {
        $owner = $this->account();

        Sanctum::actingAs($owner);
        // Sending a category on a personal account is ignored, not stored.
        $this->patchJson('/api/v1/me', [
            'category' => 'artist',
        ])->assertOk()
            ->assertJsonPath('data.user.category', null);

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'category' => null,
        ]);
    }

    public function test_downgrading_to_personal_clears_the_category(): void
    {
        $owner = $this->account();
        $owner->forceFill(['account_type' => AccountType::Business->value, 'category' => 'artist'])->save();

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Personal->value,
        ])->assertOk()
            ->assertJsonPath('data.user.category', null)
            ->assertJsonPath('data.user.account_type', 'personal');

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'category' => null,
        ]);
    }

    public function test_the_category_validates_against_the_catalogue(): void
    {
        $owner = $this->account();

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Business->value,
            'category' => 'destroyer-of-worlds',
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'category');
    }

    public function test_the_category_badge_is_visible_on_the_public_profile(): void
    {
        $owner = $this->account();
        $owner->forceFill([
            'account_type' => AccountType::Business->value,
            'category' => 'entertainment',
        ])->save();

        Sanctum::actingAs($this->account());
        $this->getJson("/api/v1/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.user.category', 'entertainment')
            ->assertJsonPath('data.user.category_label', 'Entertainment');
    }

    public function test_the_categories_endpoint_lists_the_catalogue(): void
    {
        Sanctum::actingAs($this->account());

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.categories.0.key', 'artist')
            ->assertJsonStructure([
                'data' => ['categories' => [['key', 'label']]],
            ]);
    }

    public function test_an_account_starts_public(): void
    {
        $owner = $this->account();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.is_private', false);
    }

    public function test_an_account_can_be_made_private(): void
    {
        $owner = $this->account();

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/me', ['is_private' => true])
            ->assertOk()
            ->assertJsonPath('data.user.is_private', true);

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'is_private' => true,
        ]);
    }

    public function test_a_private_account_is_visible_as_private_to_visitors(): void
    {
        $owner = $this->account();
        $owner->forceFill(['is_private' => true])->save();

        Sanctum::actingAs($this->account());
        $this->getJson("/api/v1/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.user.is_private', true);
    }

    public function test_a_private_account_cannot_be_a_professional_or_business(): void
    {
        $owner = $this->account();
        $owner->forceFill(['is_private' => true])->save();

        Sanctum::actingAs($owner);
        // The client never sends this (the editor locks private → personal),
        // but the backend must not store a contradiction if it ever arrives.
        $this->patchJson('/api/v1/me', [
            'account_type' => AccountType::Business->value,
            'is_private' => true,
        ])->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $owner->id,
            'account_type' => 'business',
            'is_private' => true,
        ]);
    }

    public function test_a_stranger_cannot_see_a_private_accounts_posts(): void
    {
        $owner = $this->account();
        $owner->forceFill(['is_private' => true])->save();
        $this->postBy($owner);

        Sanctum::actingAs($this->account());
        $this->getJson("/api/v1/users/{$owner->id}/posts")
            ->assertOk()
            ->assertJsonPath('data.posts', []);
    }

    public function test_a_follower_can_see_a_private_accounts_posts(): void
    {
        $owner = $this->account();
        $owner->forceFill(['is_private' => true])->save();
        $post = $this->postBy($owner);

        $follower = $this->account();
        $this->follow($follower, $owner);

        Sanctum::actingAs($follower);
        $this->getJson("/api/v1/users/{$owner->id}/posts")
            ->assertOk()
            ->assertJsonPath('data.posts.0.id', $post->id);
    }

    public function test_the_owner_always_sees_their_own_private_posts(): void
    {
        $owner = $this->account();
        $owner->forceFill(['is_private' => true])->save();
        $post = $this->postBy($owner);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/users/{$owner->id}/posts")
            ->assertOk()
            ->assertJsonPath('data.posts.0.id', $post->id);
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

    private function postBy(User $author): Post
    {
        return Post::factory()->create(['user_id' => $author->id]);
    }

    private function follow(User $follower, User $following): void
    {
        Follow::query()->create([
            'follower_id' => $follower->id,
            'following_id' => $following->id,
        ]);
    }
}
