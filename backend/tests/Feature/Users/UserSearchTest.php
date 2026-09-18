<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_users_by_username(): void
    {
        $viewer = User::factory()->create();
        $alice = User::factory()->create(['username' => 'alice_wonder']);
        $mallory = User::factory()->create(['username' => 'mal_ice']);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/users/search?query=ali')
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.username', 'alice_wonder');
    }

    public function test_search_matches_display_name_and_prioritises_prefix_hits(): void
    {
        $viewer = User::factory()->create();
        $prefix = User::factory()->create(['username' => 'may', 'display_name' => 'Maya Rose']);
        $middle = User::factory()->create(['username' => 'mayra', 'display_name' => 'Amy Tan']);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/users/search?query=may')
            ->assertOk()
            ->assertJsonCount(2, 'data.users')
            ->assertJsonPath('data.users.0.username', 'may')
            ->assertJsonPath('data.users.1.username', 'mayra');
    }

    public function test_search_requires_auth_and_limits_results(): void
    {
        foreach (range(1, 12) as $i) {
            User::factory()->create(['username' => "friend_{$i}"]);
        }

        $this->getJson('/api/v1/users/search?query=friend')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users/search?query=friend')
            ->assertOk()
            ->assertJsonCount(8, 'data.users');
    }

    public function test_empty_query_returns_no_users(): void
    {
        $viewer = User::factory()->create();

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/users/search?query=')
            ->assertOk()
            ->assertJsonCount(0, 'data.users');
    }
}
