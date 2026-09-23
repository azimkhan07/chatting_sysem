<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Admin\Models\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Billing\Contracts\SubscriptionRepository;
use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_forbidden_from_admin_endpoints(): void
    {
        $user = User::factory()->create();
        $subscription = $this->pendingFor($user);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/subscriptions')
            ->assertStatus(403);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/subscriptions/stats')
            ->assertStatus(403);

        $this->actingAs($user)
            ->postJson("/api/v1/admin/subscriptions/{$subscription->id}/approve")
            ->assertStatus(403);

        $this->actingAs($user)
            ->postJson("/api/v1/admin/subscriptions/{$subscription->id}/reject")
            ->assertStatus(403);
    }

    public function test_admin_can_list_and_filter_subscriptions(): void
    {
        $admin = $this->admin();
        $seller = User::factory()->create();
        $pending = $this->pendingFor($seller);
        $active = $this->activate($seller, Plan::Pro);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/subscriptions?status=pending')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.subscriptions.0.id', $pending->id)
            ->assertJsonPath('data.subscriptions.0.user.username', $seller->username)
            ->assertJsonPath('data.subscriptions.0.status_code', 'pending');

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/subscriptions?status=active')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.subscriptions.0.id', $active->id)
            ->assertJsonPath('data.subscriptions.0.status_code', 'active')
            ->assertJsonPath('data.subscriptions.0.user.is_verified', true);
    }

    public function test_stats_returns_counts_and_net_revenue(): void
    {
        $admin = $this->admin();
        $seller = User::factory()->create();

        $this->pendingFor($seller, Plan::Basic);
        $this->activate($seller, Plan::Basic);
        $this->activate($seller, Plan::Pro);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/subscriptions/stats')
            ->assertOk()
            ->assertJsonPath('data.stats.counts.pending', 1)
            ->assertJsonPath('data.stats.counts.active', 2)
            ->assertJsonPath('data.stats.revenue_paisa', 600);
    }

    public function test_admin_approves_a_paid_subscription_and_badge_goes_live(): void
    {
        $admin = $this->admin();
        $seller = User::factory()->create();
        $subscription = $this->pendingFor($seller);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/subscriptions/{$subscription->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.subscription.status_code', 'active')
            ->assertJsonPath('data.subscription.user.is_verified', true)
            ->assertJsonPath('data.subscription.verified_at', $subscription->refresh()->verified_at?->toIso8601String());

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::Active->value,
            'approved_by' => $admin->id,
        ]);
        $this->assertSame(true, $seller->refresh()->is_verified);
    }

    public function test_admin_rejects_a_subscription_and_payment_is_refunded(): void
    {
        $admin = $this->admin();
        $seller = User::factory()->create();
        $subscription = $this->pendingFor($seller);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/subscriptions/{$subscription->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.subscription.status_code', 'refunded')
            ->assertJsonPath('data.subscription.user.is_verified', false);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::Refunded->value,
        ]);
        $this->assertSame(false, $seller->refresh()->is_verified);
    }

    public function test_missing_subscription_returns_not_found(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/subscriptions/9999/approve')
            ->assertStatus(404);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::query()->where('name', 'admin')->firstOrFail();
        $admin->roles()->attach($role->id);

        return $admin->refresh();
    }

    private function pendingFor(User $user, Plan $plan = Plan::Basic): Subscription
    {
        $subscription = $this->repository()->create((int) $user->id, $plan);

        return $this->repository()->recordPayment($subscription, 'mock_ck_paid_1234');
    }

    private function activate(User $user, Plan $plan): Subscription
    {
        $adminId = (int) $this->admin()->id;

        return $this->repository()->approve(
            $this->repository()->create((int) $user->id, $plan),
            $adminId,
        );
    }

    private function repository(): SubscriptionRepository
    {
        return app(SubscriptionRepository::class);
    }
}
