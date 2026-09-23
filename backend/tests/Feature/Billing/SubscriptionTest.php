<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domain\Admin\Models\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Billing\Contracts\SubscriptionRepository;
use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SubscriptionService;
use App\Domain\Social\Models\UserNotification;
use App\Events\NotificationCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_approve_grants_the_blue_badge_and_activation_window(): void
    {
        $user = User::factory()->create();
        $subscription = $this->service()->verify((int) $user->id, Plan::Basic);
        $adminId = (int) User::factory()->create()->id;

        $this->repository()->approve($subscription, $adminId);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::Active->value,
            'approved_by' => $adminId,
        ]);
        $this->assertSame(true, $user->refresh()->is_verified);
        $this->assertNotNull($subscription->refresh()->expires_at);
    }

    public function test_cancelling_the_last_active_subscription_removes_the_badge(): void
    {
        $user = User::factory()->create();
        $subscription = $this->activate($user, Plan::Basic);
        $this->assertSame(true, $user->refresh()->is_verified);

        $this->service()->cancel($user, $subscription);

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);
        $this->assertSame(false, $user->refresh()->is_verified);
        $this->assertSame(false, $subscription->refresh()->auto_renew);
    }

    public function test_cancelling_keeps_badge_when_another_subscription_stays_active(): void
    {
        $user = User::factory()->create();
        $first = $this->activate($user, Plan::Basic);
        $adminId = (int) User::factory()->create()->id;
        $second = $this->repository()->approve(
            $this->repository()->create((int) $user->id, Plan::Pro),
            $adminId,
        );

        $this->service()->cancel($user, $first);

        $this->assertSame(SubscriptionStatus::Active, $second->refresh()->status);
        $this->assertSame(true, $user->refresh()->is_verified);
    }

    public function test_process_due_auto_renews_active_auto_renew_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = $this->activate($user, Plan::Basic);
        $subscription->forceFill(['expires_at' => now()->subMinute()])->save();

        $result = $this->service()->processDue();

        $this->assertSame(['renewed' => 1, 'expired' => 0], $result);
        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertTrue($subscription->expires_at->isAfter(now()));
        $this->assertSame(true, $user->refresh()->is_verified);
    }

    public function test_process_due_expires_non_renewing_subscription_and_revokes_badge(): void
    {
        $user = User::factory()->create();
        $subscription = $this->activate($user, Plan::Basic);
        $subscription->forceFill([
            'auto_renew' => false,
            'expires_at' => now()->subMinute(),
        ])->save();

        $result = $this->service()->processDue();

        $this->assertSame(['renewed' => 0, 'expired' => 1], $result);
        $this->assertSame(SubscriptionStatus::Expired, $subscription->refresh()->status);
        $this->assertSame(false, $user->refresh()->is_verified);
    }

    public function test_paying_for_verification_notifies_admins_of_pending_review(): void
    {
        Event::fake([NotificationCreated::class]);

        $admin = $this->withAdminRole(User::factory()->create());
        $otherAdmin = $this->withAdminRole(User::factory()->create());
        $buyer = User::factory()->create();
        $subscription = $this->service()->verify((int) $buyer->id, Plan::Basic);

        $this->service()->recordPayment($buyer, $subscription, 'mock', 'mock_ck_paid_token');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'actor_id' => $buyer->id,
            'type' => 'admin_review',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $otherAdmin->id,
            'actor_id' => $buyer->id,
            'type' => 'admin_review',
        ]);

        Event::assertDispatched(NotificationCreated::class, fn (NotificationCreated $event): bool => $event->userId === $admin->id);
        Event::assertDispatched(NotificationCreated::class, fn (NotificationCreated $event): bool => $event->userId === $otherAdmin->id);

        $this->assertSame(true, UserNotification::query()->where('user_id', $admin->id)->first()?->data['subscription_id'] === $subscription->id);
    }

    private function withAdminRole(User $user): User
    {
        $role = Role::query()->where('name', 'admin')->firstOrFail();
        $user->roles()->attach($role->id);

        return $user->refresh();
    }

    private function activate(User $user, Plan $plan): Subscription
    {
        $subscription = $this->service()->verify((int) $user->id, $plan);
        $adminId = (int) User::factory()->create()->id;

        return $this->repository()->approve($subscription, $adminId);
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function repository(): SubscriptionRepository
    {
        return app(SubscriptionRepository::class);
    }
}
