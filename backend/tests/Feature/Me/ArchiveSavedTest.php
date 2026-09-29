<?php

declare(strict_types=1);

namespace Tests\Feature\Me;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use App\Domain\Saved\Models\SavedCollection;
use App\Domain\Saved\Models\SavedItem;
use App\Domain\Stories\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ArchiveSavedTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(): User
    {
        /** @var User $user */
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }

    private function newStory(int $userId, Carbon $expiresAt): Story
    {
        /** @var Story $story */
        $story = Story::query()->create([
            'user_id' => $userId,
            'type' => 'image',
            'mime' => 'image/png',
            'media_url' => 'https://example.org/story.png',
            'expires_at' => $expiresAt,
        ]);

        return $story;
    }

    public function test_archive_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/archive/calendar')->assertUnauthorized();
        $this->getJson('/api/v1/me/saved')->assertUnauthorized();
    }

    public function test_archiving_hides_post_from_profile_and_public_feed(): void
    {
        $me = $this->subscriber();
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        /** @var Post $post */
        $post = Post::factory()->create(['user_id' => $me->id]);

        Sanctum::actingAs($me);
        $this->postJson("/api/v1/posts/{$post->id}/archive")->assertOk();

        // Hidden from the profile grid.
        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/users/{$me->username}/posts")->assertOk()
            ->assertJsonCount(0, 'data.posts');

        // Hidden from the home feed.
        $this->getJson('/api/v1/posts')->assertOk()
            ->assertJsonMissing(['id' => $post->id]);
    }

    public function test_only_owner_can_archive_a_post(): void
    {
        $me = $this->subscriber();
        /** @var Post $post */
        $post = Post::factory()->create(['user_id' => $me->id]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/posts/{$post->id}/archive")->assertForbidden();
    }

    public function test_unarchive_restores_post(): void
    {
        $me = $this->subscriber();
        /** @var Post $post */
        $post = Post::factory()->create([
            'user_id' => $me->id,
            'archived_at' => now(),
        ]);

        $this->postJson("/api/v1/posts/{$post->id}/unarchive")->assertOk()
            ->assertJsonPath('data.archived', false);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'archived_at' => null,
        ]);
    }

    public function test_calendar_counts_only_days_with_archived_content(): void
    {
        $me = $this->subscriber();

        Post::factory()->create(['user_id' => $me->id, 'archived_at' => now()]);
        Post::factory()->create(['user_id' => $me->id]); // not archived

        $this->newStory($me->id, now()->subHour());
        // An unexpired story is still live, so not on the calendar.
        $this->newStory($me->id, now()->addHour());

        $this->getJson('/api/v1/me/archive/calendar')
            ->assertOk()
            ->assertJsonPath('data.year', now()->year);

        $response = $this->getJson('/api/v1/me/archive/calendar')->json();
        $this->assertArrayHasKey(now()->toDateString(), $response['data']['days']);
        $this->assertSame(1, $response['data']['days'][now()->toDateString()]['posts']);
        $this->assertSame(1, $response['data']['days'][now()->toDateString()]['stories']);
    }

    public function test_archive_posts_returns_only_that_day(): void
    {
        $me = $this->subscriber();

        Post::factory()->create(['user_id' => $me->id, 'archived_at' => now()]);
        /** @var Post $yesterday */
        $yesterday = Post::factory()->create([
            'user_id' => $me->id,
            'created_at' => now()->subDay(),
            'archived_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/me/archive/posts?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data.posts')
            ->assertJsonMissing(['id' => $yesterday->id]);
    }

    public function test_archive_stories_only_expired_ones_of_that_day(): void
    {
        $me = $this->subscriber();

        $this->newStory($me->id, now()->subHour());
        $this->newStory($me->id, now()->addDay());

        $this->getJson('/api/v1/me/archive/stories?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data.stories');
    }

    public function test_save_creates_item_and_collection(): void
    {
        $me = $this->subscriber();
        /** @var Post $post */
        $post = Post::factory()->create();

        $collection = $this->postJson('/api/v1/me/saved/collections', ['name' => 'Ideas'])
            ->assertCreated()
            ->json('data.collection');

        $this->postJson('/api/v1/me/saved', [
            'saveable_type' => 'post',
            'saveable_id' => $post->id,
            'collection_id' => $collection['id'],
        ])->assertOk()->assertJsonPath('data.created', true);

        $this->assertDatabaseHas('saved_items', [
            'user_id' => $me->id,
            'saveable_type' => (new Post)->getMorphClass(),
            'saveable_id' => $post->id,
        ]);

        $this->getJson('/api/v1/me/saved')
            ->assertOk()
            ->assertJsonPath('data.collections.0.name', 'Ideas');

        // Item lands in the collection folder too.
        $this->getJson("/api/v1/me/saved/collections/{$collection['id']}/items")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_unsave_removes_item(): void
    {
        $me = $this->subscriber();
        /** @var Post $post */
        $post = Post::factory()->create();

        $this->postJson('/api/v1/me/saved', [
            'saveable_type' => 'post',
            'saveable_id' => $post->id,
        ])->assertOk();

        $this->deleteJson('/api/v1/me/saved', [
            'saveable_type' => 'post',
            'saveable_id' => $post->id,
        ])->assertOk()->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('saved_items', [
            'user_id' => $me->id,
            'saveable_id' => $post->id,
        ]);
    }

    public function test_save_story_item_is_listed(): void
    {
        $me = $this->subscriber();
        $story = $this->newStory(User::factory()->create()->id, now()->addHour());

        $this->postJson('/api/v1/me/saved', [
            'saveable_type' => 'story',
            'saveable_id' => $story->id,
        ])->assertOk()->assertJsonPath('data.saved.saveable_type', 'story');

        $this->getJson('/api/v1/me/saved')
            ->assertOk()
            ->assertJsonPath('data.items.0.saveable_type', 'story');
    }

    public function test_delete_collection_keeps_items_in_all(): void
    {
        $me = $this->subscriber();
        /** @var Post $post */
        $post = Post::factory()->create();

        /** @var SavedCollection $collection */
        $collection = SavedCollection::query()->create(['user_id' => $me->id, 'name' => 'Folder']);
        $item = SavedItem::query()->create([
            'user_id' => $me->id,
            'saveable_type' => (new Post)->getMorphClass(),
            'saveable_id' => $post->id,
        ]);
        $collection->items()->attach($item->id);

        $this->deleteJson("/api/v1/me/saved/collections/{$collection->id}")->assertOk();

        $this->assertDatabaseMissing('saved_collections', ['id' => $collection->id]);
        $this->assertDatabaseHas('saved_items', ['id' => $item->id]);

        $this->getJson('/api/v1/me/saved')->assertOk()->assertJsonCount(1, 'data.items');
    }
}
