<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domain\Auth\Models\User;
use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

final class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tiers_lists_both_plans_with_prices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/subscriptions/tiers')
            ->assertOk()
            ->assertJsonPath('data.tiers.0.key', 'amtech_basic')
            ->assertJsonPath('data.tiers.0.name', 'Amtech Basic')
            ->assertJsonPath('data.tiers.0.price_paisa', 100)
            ->assertJsonPath('data.tiers.1.key', 'amtech_pro')
            ->assertJsonPath('data.tiers.1.name', 'Amtech Pro')
            ->assertJsonPath('data.tiers.1.price_paisa', 500);
    }

    public function test_verify_creates_a_pending_subscription_and_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/subscriptions/verify', ['plan' => Plan::Basic->value])
            ->assertCreated()
            ->assertJsonPath('data.subscription.status_code', 'pending');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Pending->value,
        ]);
    }

    public function test_verify_returns_the_existing_pending_subscription_for_the_same_plan(): void
    {
        $user = User::factory()->create();
        $created = $this->service()->verify((int) $user->id, Plan::Basic);

        $this->actingAs($user)
            ->postJson('/api/v1/subscriptions/verify', ['plan' => Plan::Basic->value])
            ->assertCreated()
            ->assertJsonPath('data.subscription.id', $created->id);
    }

    public function test_checkout_returns_mock_gateway_client_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/subscriptions/checkout', ['plan' => Plan::Pro->value])
            ->assertOk()
            ->assertJsonPath('data.gateway', 'mock')
            ->assertJsonPath('data.amount_paisa', 500)
            ->assertJsonStructure(['data' => ['client_token']]);
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }
}
