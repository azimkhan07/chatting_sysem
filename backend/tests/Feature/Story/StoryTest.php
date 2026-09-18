<?php

declare(strict_types=1);

namespace Tests\Feature\Story;

use App\Domain\Auth\Models\User;
use App\Domain\Stories\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeMedia;
use Tests\TestCase;

final class StoryTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_user_can_post_a_story(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/stories', [
                'media' => FakeMedia::png(600, 800),
                'caption' => 'Morning vibes',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.story.type', 'image')
            ->assertJsonPath('data.story.caption', 'Morning vibes');

        $this->assertDatabaseHas('stories', [
            'user_id' => $user->id,
            'type' => 'image',
            'caption' => 'Morning vibes',
        ]);
    }

    public function test_story_requires_media(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/stories', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'media');
    }

    public function test_feed_returns_active_stories_grouped_by_user(): void
    {
        $viewer = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Story::query()->create([
            'user_id' => $bob->id,
            'media_path' => 'stories/bob/c.mp4',
            'type' => 'video',
            'expires_at' => now()->addHours(20),
        ]);
        Story::query()->create([
            'user_id' => $alice->id,
            'media_path' => 'stories/alice/a.png',
            'type' => 'image',
            'expires_at' => now()->addHours(12),
        ]);
        Story::query()->create([
            'user_id' => $alice->id,
            'media_path' => 'stories/alice/b.png',
            'type' => 'image',
            'expires_at' => now()->addMinutes(59),
        ]);

        $this->withToken($this->tokenFor($viewer))
            ->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonCount(2, 'data.stories')
            ->assertJsonPath('data.stories.0.user.username', $alice->username)
            ->assertJsonCount(2, 'data.stories.0.stories')
            ->assertJsonPath('data.stories.1.user.username', $bob->username)
            ->assertJsonPath('data.stories.1.stories.0.type', 'video');
    }

    public function test_expired_stories_are_hidden_from_feed(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();

        Story::query()->create([
            'user_id' => $owner->id,
            'media_path' => 'stories/owner/old.png',
            'type' => 'image',
            'expires_at' => now()->subMinute(),
        ]);
        Story::query()->create([
            'user_id' => $owner->id,
            'media_path' => 'stories/owner/fresh.png',
            'type' => 'image',
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($this->tokenFor($viewer))
            ->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonCount(1, 'data.stories.0.stories');
    }

    public function test_only_the_owner_may_delete_a_story(): void
    {
        $owner = User::factory()->create();
        $trespasser = User::factory()->create();
        $story = Story::query()->create([
            'user_id' => $owner->id,
            'media_path' => 'stories/owner/x.png',
            'type' => 'image',
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($this->tokenFor($trespasser))
            ->deleteJson("/api/v1/stories/{$story->id}")
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');

        $this->assertDatabaseCount('stories', 1);
    }

    public function test_owner_can_delete_story(): void
    {
        $owner = User::factory()->create();
        $story = Story::query()->create([
            'user_id' => $owner->id,
            'media_path' => 'stories/owner/x.png',
            'type' => 'image',
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($this->tokenFor($owner))
            ->deleteJson("/api/v1/stories/{$story->id}")
            ->assertOk();

        $this->assertDatabaseCount('stories', 0);
    }

    public function test_stories_require_authentication(): void
    {
        $this->getJson('/api/v1/stories')->assertStatus(401);
        $this->post('/api/v1/stories', ['media' => FakeMedia::png(100, 100)], ['Accept' => 'application/json'])
            ->assertStatus(401);
    }
}
