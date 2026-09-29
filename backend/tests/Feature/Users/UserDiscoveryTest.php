<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Social\Models\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class UserDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_lists_highest_reach_first(): void
    {
        $viewer = $this->account();
        $small = $this->account();
        $large = $this->account();
        $medium = $this->account();

        // Reach is built from accounts the viewer does *not* follow, since a
        // followed account is (by design) absent from the list.
        $this->followMany($large, [$this->account()->id, $this->account()->id, $this->account()->id]);
        $this->followMany($medium, [$this->account()->id, $this->account()->id]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/v1/users/top');

        $response->assertOk()
            ->assertJsonPath('data.users.0.id', $large->id)
            ->assertJsonPath('data.users.1.id', $medium->id)
            ->assertJsonPath('data.users.2.id', $small->id);

        $ids = collect($response->json('data.users'))->pluck('id')->all();
        $this->assertNotContains($viewer->id, $ids, 'The viewer never sees themselves.');
    }

    public function test_top_excludes_accounts_already_followed(): void
    {
        $viewer = $this->account();
        $followed = $this->account();
        $this->followMany($followed, [$viewer->id, $this->account()->id]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/v1/users/top');

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();
        $this->assertNotContains($followed->id, $ids);
    }

    public function test_top_hides_disabled_accounts(): void
    {
        $disabled = $this->account();
        $disabled->forceFill(['status' => UserStatus::Suspended->value])->save();

        Sanctum::actingAs($this->account());

        $this->getJson('/api/v1/users/top')
            ->assertOk()
            ->assertJsonMissing(['id' => $disabled->id]);
    }

    public function test_top_requires_authentication(): void
    {
        $this->getJson('/api/v1/users/top')->assertUnauthorized();
    }

    public function test_match_contacts_returns_registered_numbers(): void
    {
        Sanctum::actingAs($this->account());
        $friend = $this->account(['mobile' => '9876543210']);

        $response = $this->postJson('/api/v1/users/match-contacts', [
            'contacts' => ['+91 98765 43210', '9999999999'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.users.0.id', $friend->id)
            ->assertJsonPath('data.matched', 1)
            ->assertJsonPath('data.checked', 2);
    }

    public function test_match_contacts_accepts_bare_and_prefixed_forms(): void
    {
        Sanctum::actingAs($this->account());
        $friend = $this->account(['mobile' => '919876543210']);

        $this->postJson('/api/v1/users/match-contacts', ['contacts' => ['9876543210']])
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $friend->id);
    }

    public function test_match_contacts_never_returns_the_viewer(): void
    {
        Sanctum::actingAs($this->account(['mobile' => '9876500000']));

        $this->postJson('/api/v1/users/match-contacts', ['contacts' => ['9876500000']])
            ->assertOk()
            ->assertJsonCount(0, 'data.users')
            ->assertJsonPath('data.matched', 0);
    }

    public function test_match_contacts_ignores_disabled_accounts(): void
    {
        Sanctum::actingAs($this->account());
        $disabled = $this->account(['mobile' => '9876543210']);
        $disabled->forceFill(['status' => UserStatus::Suspended->value])->save();

        $this->postJson('/api/v1/users/match-contacts', ['contacts' => ['9876543210']])
            ->assertOk()
            ->assertJsonCount(0, 'data.users');
    }

    public function test_match_contacts_validates_input(): void
    {
        Sanctum::actingAs($this->account());

        $this->postJson('/api/v1/users/match-contacts', ['contacts' => []])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'contacts');

        $this->postJson('/api/v1/users/match-contacts', ['contacts' => ['not-a-number']])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'contacts.0');
    }

    public function test_match_contacts_requires_authentication(): void
    {
        $this->postJson('/api/v1/users/match-contacts', ['contacts' => ['9876543210']])
            ->assertUnauthorized();
    }

    public function test_top_hides_blocked_accounts(): void
    {
        $viewer = $this->account();
        $blocked = $this->account();
        $this->followMany($blocked, [$this->account()->id, $this->account()->id, $this->account()->id]);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}");

        // "Top accounts" is a suggested-who-to-follow rail. A blocked account
        // appearing there would surface the very person the viewer has hidden.
        $response = $this->getJson('/api/v1/users/top');
        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();
        $this->assertNotContains($blocked->id, $ids);
    }

    public function test_match_contacts_hides_blocked_accounts(): void
    {
        $viewer = $this->account();
        $blocked = $this->account(['mobile' => '9876543210']);

        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/moderation/blocks/{$blocked->id}");

        // If the blocker's own phone book matched the blocked account, handing
        // the account back out of match-contacts would undo the profile block.
        $this->postJson('/api/v1/users/match-contacts', ['contacts' => ['9876543210']])
            ->assertOk()
            ->assertJsonCount(0, 'data.users')
            ->assertJsonPath('data.matched', 0);
    }

    /** A plain active account; auth is attached explicitly, as the other suites do. */
    private function account(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * @param  list<int>  $followers
     */
    private function followMany(User $account, array $followers): void
    {
        foreach ($followers as $followerId) {
            Follow::query()->create([
                'follower_id' => $followerId,
                'following_id' => $account->id,
                'created_at' => now(),
            ]);
        }
    }
}
