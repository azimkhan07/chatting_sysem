<?php

declare(strict_types=1);

namespace Tests\Feature\Moderation;

use App\Domain\Admin\Models\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Moderation\Enums\ReportReason;
use App\Domain\Moderation\Enums\ReportStatus;
use App\Domain\Moderation\Models\Report;
use App\Domain\Posts\Models\Comment;
use App\Domain\Posts\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_requires_authentication(): void
    {
        $post = $this->postBy(User::factory()->create());

        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => ReportReason::Spam->value,
        ])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED');
    }

    public function test_a_user_can_report_someone_elses_post(): void
    {
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $post = $this->postBy($author);

        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => ReportReason::Spam->value,
            'details' => 'same three posts, every hour',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.report.target_type', 'post')
            ->assertJsonPath('data.report.status', ReportStatus::Pending->value);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => ReportReason::Spam->value,
            'status' => ReportStatus::Pending->value,
        ]);
    }

    public function test_reporting_your_own_post_is_rejected(): void
    {
        $user = User::factory()->create();
        $post = $this->postBy($user);

        Sanctum::actingAs($user);

        // A self-report is either a confused user or a way to push your own
        // content into the queue, and a queue full of those is one staff stop
        // trusting.
        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => ReportReason::Spam->value,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_OPERATION');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_reporting_your_own_account_is_rejected(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'user',
            'target_id' => $user->id,
            'reason' => ReportReason::FakeAccount->value,
        ])->assertStatus(422);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_an_unknown_reason_is_rejected(): void
    {
        $reporter = User::factory()->create();
        $post = $this->postBy(User::factory()->create());

        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'because_i_said_so',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_reporting_a_post_that_does_not_exist_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'post',
            'target_id' => 999999,
            'reason' => ReportReason::Spam->value,
        ])->assertStatus(422);
    }

    public function test_the_same_complaint_twice_is_one_report(): void
    {
        $reporter = User::factory()->create();
        $post = $this->postBy(User::factory()->create());

        Sanctum::actingAs($reporter);

        $payload = [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => ReportReason::Spam->value,
        ];

        $this->postJson('/api/v1/moderation/reports', $payload)->assertStatus(201);
        $this->postJson('/api/v1/moderation/reports', $payload)->assertStatus(201);

        // Duplicates bury everyone else's reports, so the queue is keyed on
        // (reporter, target, reason).
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_two_people_reporting_the_same_post_are_two_reports(): void
    {
        $post = $this->postBy(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => ReportReason::Spam->value,
        ])->assertStatus(201);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => ReportReason::Spam->value,
        ])->assertStatus(201);

        $this->assertDatabaseCount('reports', 2);
    }

    public function test_a_comment_can_be_reported(): void
    {
        $reporter = User::factory()->create();
        $post = $this->postBy(User::factory()->create());
        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'rude',
        ]);

        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'reason' => ReportReason::Harassment->value,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.report.target_type', 'comment');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'target_type' => 'comment',
            'target_id' => $comment->id,
        ]);
    }

    public function test_a_message_can_only_be_reported_from_inside_its_conversation(): void
    {
        $outsider = User::factory()->create();
        $author = User::factory()->create();
        $member = User::factory()->create();

        $conversation = Conversation::query()->create([
            'type' => 'dm',
            'created_by' => $author->id,
        ]);
        foreach ([$author, $member] as $person) {
            ConversationMember::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $person->id,
            ]);
        }
        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'type' => 'text',
            'body' => 'something worth reporting',
        ]);

        $payload = [
            'target_type' => 'message',
            'target_id' => $message->id,
            'reason' => ReportReason::Abuse->value,
        ];

        Sanctum::actingAs($outsider);
        // Without the membership check the id is a capability: any signed-up
        // user could report a private message they have never seen.
        $this->postJson('/api/v1/moderation/reports', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.message', 'That message is not available to you.');

        $this->assertDatabaseCount('reports', 0);

        Sanctum::actingAs($member);
        $this->postJson('/api/v1/moderation/reports', $payload)->assertStatus(201);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $member->id,
            'target_type' => 'message',
            'target_id' => $message->id,
        ]);
    }

    public function test_the_reason_list_drives_the_client_picker(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/moderation/report-reasons')
            ->assertOk()
            ->assertJsonPath('data.reasons.0.value', ReportReason::Spam->value)
            // The client renders from this list rather than a hardcoded copy,
            // so it cannot offer a reason the server will reject.
            ->assertJsonPath('data.reasons.0.is_urgent', false)
            ->assertJsonPath('data.target_types.0.value', 'user');
    }

    public function test_the_queue_is_closed_to_ordinary_users(): void
    {
        $report = $this->fileReport();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/reports')->assertStatus(403);

        $this->patchJson("/api/v1/admin/reports/{$report->id}", [
            'status' => ReportStatus::Dismissed->value,
        ])->assertStatus(403);
    }

    public function test_staff_see_the_queue_with_the_reported_account(): void
    {
        $report = $this->fileReport();

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/admin/reports')
            ->assertOk()
            ->assertJsonCount(1, 'data.reports')
            ->assertJsonPath('data.reports.0.id', $report->id)
            ->assertJsonPath('data.reports.0.reason', ReportReason::Spam->value)
            // Staff cannot triage a complaint without knowing who is behind
            // the thing being complained about.
            ->assertJsonPath('data.reports.0.target_user.username', $this->postAuthorOf($report)->username);
    }

    public function test_a_reporter_cannot_read_the_queue_they_filed_into(): void
    {
        $report = $this->fileReport();

        Sanctum::actingAs($report->reporter);

        // The queue names other people's complaints, so the person who filed
        // one gets a confirmation and nothing else.
        $this->getJson('/api/v1/admin/reports')->assertStatus(403);
    }

    public function test_the_queue_puts_urgent_reports_first(): void
    {
        $spam = $this->fileReport(ReportReason::Spam);
        $urgent = $this->fileReport(ReportReason::SelfHarm);

        Sanctum::actingAs($this->admin());

        $ids = $this->getJson('/api/v1/admin/reports')->json('data.reports.*.id');

        // Sorted by severity, not age: the whole point of a queue is that the
        // worst thing in it is worked first.
        $this->assertSame([$urgent->id, $spam->id], $ids);
    }

    public function test_staff_can_close_a_report(): void
    {
        $report = $this->fileReport();
        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/reports/{$report->id}", [
            'status' => ReportStatus::Actioned->value,
            'resolution' => 'post removed',
        ])
            ->assertOk()
            ->assertJsonPath('data.report.status', ReportStatus::Actioned->value)
            ->assertJsonPath('data.report.resolution', 'post removed');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => ReportStatus::Actioned->value,
            'handled_by' => $admin->id,
        ]);
    }

    public function test_a_closed_report_leaves_the_queue(): void
    {
        $report = $this->fileReport();

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/v1/admin/reports/{$report->id}", [
            'status' => ReportStatus::Dismissed->value,
        ])->assertOk();

        $this->getJson('/api/v1/admin/reports')->assertJsonCount(0, 'data.reports');
    }

    public function test_a_report_cannot_be_pushed_back_to_pending(): void
    {
        $report = $this->fileReport();

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/v1/admin/reports/{$report->id}", [
            'status' => ReportStatus::Pending->value,
        ])->assertStatus(422);
    }

    private function fileReport(ReportReason $reason = ReportReason::Spam): Report
    {
        $reporter = User::factory()->create();
        $post = $this->postBy(User::factory()->create());

        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/moderation/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => $reason->value,
            'details' => 'details from the reporter',
        ])->assertStatus(201);

        return Report::query()->where('reporter_id', $reporter->id)->firstOrFail();
    }

    private function postAuthorOf(Report $report): User
    {
        return User::query()
            ->findOrFail(Post::query()->whereKey($report->target_id)->value('user_id'));
    }

    private function postBy(User $author): Post
    {
        return Post::query()->create([
            'user_id' => $author->id,
            'body' => 'a post',
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::query()->where('name', 'admin')->firstOrFail();
        $admin->roles()->attach($role->id);

        return $admin->refresh();
    }
}
