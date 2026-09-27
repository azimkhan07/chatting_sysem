<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domain\Auth\Models\User;
use App\Domain\Family\Enums\FamilyRole;
use App\Domain\Family\Models\FamilyGroup;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Family\Services\FamilyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class FamilyCenterTest extends TestCase
{
    use RefreshDatabase;

    private FamilyService $families;

    protected function setUp(): void
    {
        parent::setUp();

        $this->families = app(FamilyService::class);
    }

    public function test_it_reports_no_family_for_a_user_without_one(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/family');

        $response->assertOk()->assertJsonPath('data.family', null);
    }

    public function test_creating_a_family_makes_the_creator_a_guardian_owner(): void
    {
        $owner = User::factory()->create(['display_name' => 'Ravi']);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/me/family', [
            'name' => 'The Sharma household',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.family.name', 'The Sharma household')
            ->assertJsonPath('data.family.viewer.role', FamilyRole::Guardian->value)
            ->assertJsonPath('data.family.viewer.is_owner', true)
            ->assertJsonPath('data.family.counts.members', 1)
            ->assertJsonPath('data.family.counts.guardians', 1)
            ->assertJsonPath('data.family.viewer.can_dissolve', true)
            ->assertJsonPath('data.family.viewer.can_rename', true)
            ->assertJsonPath('data.family.members.0.user.display_name', 'Ravi');

        $this->assertDatabaseHas('family_members', [
            'user_id' => $owner->id,
            'role' => FamilyRole::Guardian->value,
        ]);
    }

    public function test_a_user_cannot_create_a_second_family(): void
    {
        $user = User::factory()->create();
        $this->families->createFor($user, 'Household');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/family', ['name' => 'Another one'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_OPERATION');
    }

    public function test_a_guardian_adds_a_teen_by_username(): void
    {
        $parent = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);

        $this->families->createFor($parent, 'Home');

        $response = $this->actingAs($parent, 'sanctum')->postJson('/api/v1/me/family/members', [
            'username' => 'teenager',
            'role' => FamilyRole::Teen->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.family.counts.members', 2)
            ->assertJsonPath('data.family.members.1.role', FamilyRole::Teen->value)
            // The teen is not the viewer, so they may not be removed by
            // themselves and the viewer may remove them.
            ->assertJsonPath('data.family.members.1.is_self', false)
            ->assertJsonPath('data.family.members.1.permissions.can_leave', true);

        $this->assertDatabaseHas('family_members', [
            'user_id' => $teen->id,
            'role' => FamilyRole::Teen->value,
        ]);
    }

    public function test_an_unknown_username_is_a_404_not_a_validation_error(): void
    {
        $parent = User::factory()->create();
        $this->families->createFor($parent, 'Home');

        $this->actingAs($parent, 'sanctum')
            ->postJson('/api/v1/me/family/members', [
                'username' => 'nobody_here',
                'role' => FamilyRole::Adult->value,
            ])
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'NOT_FOUND');
    }

    public function test_someone_already_in_a_family_cannot_be_added_to_another(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $target = User::factory()->create(['username' => 'taken']);

        $this->families->createFor($first, 'First house');
        $this->families->createFor($second, 'Second house');
        $this->families->addMember(
            $this->families->membershipFor($second->id),
            'taken',
            FamilyRole::Adult,
        );

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/v1/me/family/members', [
                'username' => 'taken',
                'role' => FamilyRole::Adult->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_OPERATION');

        // Still in exactly one family - a rejected add must not move anyone.
        $this->assertSame(1, FamilyMember::query()->where('user_id', $target->id)->count());
    }

    public function test_a_teen_cannot_add_members(): void
    {
        $parent = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);
        $stranger = User::factory()->create(['username' => 'stranger']);

        $this->families->createFor($parent, 'Home');
        $this->families->addMember(
            $this->families->membershipFor($parent->id),
            'teenager',
            FamilyRole::Teen,
        );

        $this->actingAs($teen, 'sanctum')
            ->postJson('/api/v1/me/family/members', [
                'username' => 'stranger',
                'role' => FamilyRole::Adult->value,
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_only_the_owner_can_add_another_guardian(): void
    {
        $owner = User::factory()->create();
        $adult = User::factory()->create(['username' => 'auntie']);
        $second = User::factory()->create(['username' => 'uncle']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember(
            $this->families->membershipFor($owner->id),
            'auntie',
            FamilyRole::Adult,
        );

        // An adult manages the roster, but cannot mint a peer who could then
        // out-vote the owner.
        $this->actingAs($adult, 'sanctum')
            ->postJson('/api/v1/me/family/members', [
                'username' => 'uncle',
                'role' => FamilyRole::Guardian->value,
            ])
            ->assertForbidden();
    }

    public function test_an_adult_can_add_another_adult(): void
    {
        $owner = User::factory()->create();
        $adult = User::factory()->create(['username' => 'auntie']);
        $cousin = User::factory()->create(['username' => 'cousin']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'auntie', FamilyRole::Adult);

        $this->actingAs($adult, 'sanctum')
            ->postJson('/api/v1/me/family/members', [
                'username' => 'cousin',
                'role' => FamilyRole::Adult->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.family.counts.members', 3);
    }

    public function test_an_invalid_role_is_rejected(): void
    {
        $owner = User::factory()->create();
        $this->families->createFor($owner, 'Home');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/me/family/members', [
                'username' => 'someone',
                'role' => 'dictator',
            ])
            ->assertStatus(422)
            // This app renders its own validation envelope, so assert the shape
            // it actually returns rather than Laravel's default.
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'role');
    }

    public function test_the_owner_can_change_a_role(): void
    {
        $owner = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'teenager', FamilyRole::Teen);

        $memberId = (int) $this->families->membershipFor($teen->id)->id;

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/me/family/members/{$memberId}", ['role' => FamilyRole::Adult->value])
            ->assertOk()
            ->assertJsonPath('data.family.members.1.role', FamilyRole::Adult->value);
    }

    public function test_the_owner_role_cannot_be_changed_or_removed(): void
    {
        $owner = User::factory()->create();
        $this->families->createFor($owner, 'Home');

        $ownerMemberId = (int) $this->families->membershipFor($owner->id)->id;

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/me/family/members/{$ownerMemberId}", ['role' => FamilyRole::Teen->value])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_OPERATION');

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/me/family/members/{$ownerMemberId}")
            ->assertStatus(422);

        $this->assertDatabaseHas('family_members', [
            'user_id' => $owner->id,
            'role' => FamilyRole::Guardian->value,
        ]);
    }

    public function test_a_member_can_leave(): void
    {
        $owner = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'teenager', FamilyRole::Teen);

        $this->actingAs($teen, 'sanctum')
            ->postJson('/api/v1/me/family/leave')
            ->assertOk()
            ->assertJsonPath('data.left', true)
            ->assertJsonPath('data.family', null);

        $this->assertDatabaseMissing('family_members', ['user_id' => $teen->id]);
        // The family itself survives a member leaving.
        $this->assertDatabaseHas('family_groups', ['name' => 'Home']);
    }

    public function test_a_guardian_can_remove_a_teen(): void
    {
        $owner = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'teenager', FamilyRole::Teen);

        $memberId = (int) $this->families->membershipFor($teen->id)->id;

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/me/family/members/{$memberId}")
            ->assertOk()
            ->assertJsonPath('data.removed', true)
            ->assertJsonPath('data.family.counts.members', 1);

        $this->assertDatabaseMissing('family_members', ['user_id' => $teen->id]);
    }

    public function test_a_stranger_cannot_touch_another_family(): void
    {
        $owner = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);
        $stranger = User::factory()->create();

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'teenager', FamilyRole::Teen);

        $memberId = (int) $this->families->membershipFor($teen->id)->id;

        // 404, not 403: "forbidden" would confirm the member exists.
        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/me/family/members/{$memberId}")
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'NOT_FOUND');

        $this->assertDatabaseHas('family_members', ['user_id' => $teen->id]);
    }

    public function test_rename_and_dissolve_are_owner_only(): void
    {
        $owner = User::factory()->create();
        $adult = User::factory()->create(['username' => 'auntie']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'auntie', FamilyRole::Adult);

        $this->actingAs($adult, 'sanctum')
            ->patchJson('/api/v1/me/family', ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($adult, 'sanctum')
            ->deleteJson('/api/v1/me/family')
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->patchJson('/api/v1/me/family', ['name' => 'Sharma residence'])
            ->assertOk()
            ->assertJsonPath('data.family.name', 'Sharma residence');

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/v1/me/family')
            ->assertOk()
            ->assertJsonPath('data.dissolved', true);

        // Dissolving removes the roster too, not just the header.
        $this->assertDatabaseMissing('family_groups');
        $this->assertDatabaseMissing('family_members');
    }

    public function test_the_account_center_summarises_the_family(): void
    {
        $owner = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'teenager', FamilyRole::Teen);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/me/account')
            ->assertOk()
            ->assertJsonPath('data.account.family.name', 'Home')
            ->assertJsonPath('data.account.family.role', FamilyRole::Guardian->value)
            ->assertJsonPath('data.account.family.is_owner', true)
            ->assertJsonPath('data.account.family.members', 2);
    }

    public function test_deleting_the_owner_account_dissolves_the_family(): void
    {
        $owner = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'teenager', FamilyRole::Teen);

        Auth::forgetGuards();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/v1/me/account', ['password' => 'password'])
            ->assertOk();

        $this->assertDatabaseMissing('family_groups');
        $this->assertDatabaseMissing('family_members');
    }

    public function test_deleting_a_member_account_only_removes_their_row(): void
    {
        $owner = User::factory()->create();
        $teen = User::factory()->create(['username' => 'teenager']);

        $family = $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'teenager', FamilyRole::Teen);

        Auth::forgetGuards();

        $this->actingAs($teen, 'sanctum')
            ->deleteJson('/api/v1/me/account', ['password' => 'password'])
            ->assertOk();

        $this->assertDatabaseHas('family_groups', ['id' => $family->id]);
        $this->assertDatabaseMissing('family_members', ['user_id' => $teen->id]);
        $this->assertDatabaseHas('family_members', ['user_id' => $owner->id]);
    }

    public function test_a_suspended_user_cannot_be_added_to_a_family(): void
    {
        $owner = User::factory()->create();
        $banned = User::factory()->create([
            'username' => 'banned_user',
            'status' => 'banned',
        ]);

        $this->families->createFor($owner, 'Home');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/me/family/members', [
                'username' => 'banned_user',
                'role' => FamilyRole::Adult->value,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('family_members', ['user_id' => $banned->id]);
    }

    public function test_spending_approval_is_guardian_only(): void
    {
        $owner = User::factory()->create();
        $adult = User::factory()->create(['username' => 'auntie']);
        $teen = User::factory()->create(['username' => 'teenager']);

        $this->families->createFor($owner, 'Home');
        $this->families->addMember($this->families->membershipFor($owner->id), 'auntie', FamilyRole::Adult);
        $this->families->addMember($this->families->membershipFor($owner->id), 'teenager', FamilyRole::Teen);

        $this->assertTrue($this->families->canApproveSpending($this->families->membershipFor($owner->id)));
        $this->assertFalse($this->families->canApproveSpending($this->families->membershipFor($adult->id)));
        $this->assertFalse($this->families->canApproveSpending($this->families->membershipFor($teen->id)));
    }

    public function test_family_groups_cannot_be_orphaned_by_a_hard_deleted_user(): void
    {
        $owner = User::factory()->create();
        $this->families->createFor($owner, 'Home');

        $family = FamilyGroup::query()->firstOrFail();

        // The FK cascade is the backstop for a hard delete (an admin purge),
        // which the self-service soft delete deliberately bypasses.
        $owner->forceDelete();

        $this->assertDatabaseMissing('family_groups', ['id' => $family->id]);
        $this->assertDatabaseMissing('family_members');
    }

    public function test_family_member_role_defaults_and_cast_round_trip(): void
    {
        $owner = User::factory()->create();
        $this->families->createFor($owner, 'Home');

        $membership = FamilyMember::query()->where('user_id', $owner->id)->firstOrFail();

        $this->assertInstanceOf(FamilyRole::class, $membership->role);
        $this->assertTrue($membership->isOwner());
        $this->assertTrue($membership->canManageMembers());
    }
}
