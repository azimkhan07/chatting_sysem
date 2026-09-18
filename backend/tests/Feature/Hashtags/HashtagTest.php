<?php

declare(strict_types=1);

namespace Tests\Feature\Hashtags;

use App\Domain\Auth\Models\User;
use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Posts\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMedia;
use Tests\TestCase;

final class HashtagTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_creating_a_post_attaches_hashtags(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts', ['body' => 'Happy #weekend from #devlife ✅'])
            ->assertStatus(201)
            ->assertJsonCount(2, 'data.post.hashtags');

        $this->assertDatabaseHas('hashtags', ['name' => 'weekend']);
        $this->assertDatabaseHas('hashtags', ['name' => 'devlife']);
        $this->assertDatabaseCount('hashtag_post', 2);
    }

    public function test_hashtag_creates_without_duplicates(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts', ['body' => 'day one #daily'])
            ->assertStatus(201);
        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts', ['body' => 'day two #daily #coding'])
            ->assertStatus(201);

        $this->assertDatabaseCount('hashtags', 2);
        $this->assertDatabaseCount('hashtag_post', 3);
    }

    public function test_hashtag_page_returns_posts_carrying_the_tag(): void
    {
        $user = User::factory()->create();
        $tagged = Post::factory()->for($user)->create(['body' => 'First #sunset shot']);
        $untagged = Post::factory()->for($user)->create(['body' => 'Just a thought']);

        $sunset = Hashtag::query()->create(['name' => 'sunset']);
        $tagged->hashtags()->attach($sunset);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/hashtags/sunset')
            ->assertOk()
            ->assertJsonPath('data.hashtag.name', 'sunset')
            ->assertJsonCount(1, 'data.posts')
            ->assertJsonPath('data.posts.0.id', $tagged->id)
            ->assertJsonPath('data.posts.0.hashtags.0', 'sunset');

        $untagged->refresh();
        $this->assertNotNull($untagged);
    }

    public function test_unknown_hashtag_returns_404(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/hashtags/doesnotexist')
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'NOT_FOUND');
    }

    public function test_hashtag_search_suggests_matching_tags_most_used_first(): void
    {
        $user = User::factory()->create();
        foreach (['moon', 'moonlight', 'moonshot', 'music'] as $i => $name) {
            $post = Post::factory()->for($user)->create(['body' => "post {$i}"]);
            $hashtag = Hashtag::query()->create(['name' => $name]);
            $post->hashtags()->attach($hashtag);
        }
        // "moon" gets a second post so it ranks highest.
        $second = Post::factory()->for($user)->create(['body' => 'post again']);
        $second->hashtags()->attach(Hashtag::query()->where('name', 'moon')->first());

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/hashtags/search?query=mo')
            ->assertOk()
            ->assertJsonCount(3, 'data.hashtags')
            ->assertJsonPath('data.hashtags.0.name', 'moon');
    }

    public function test_reels_feed_returns_only_video_posts(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/posts', [
                'body' => 'a clip',
                'media' => [UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4')],
            ])
            ->assertStatus(201);

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/posts', [
                'body' => 'a photo',
                'media' => [FakeMedia::png(640, 480)],
            ])
            ->assertStatus(201);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts', ['body' => 'text only'])
            ->assertStatus(201);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/posts/reels')
            ->assertOk();

        $posts = collect($response->json('data.posts'));
        $this->assertCount(1, $posts);
        $this->assertSame('video', $posts[0]['media'][0]['type']);
    }

    public function test_explore_feed_returns_only_posts_with_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/posts', [
                'body' => 'a photo',
                'media' => [FakeMedia::png(640, 480)],
            ])
            ->assertStatus(201);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/posts', ['body' => 'text only'])
            ->assertStatus(201);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/posts/explore')
            ->assertOk();

        $posts = collect($response->json('data.posts'));
        $this->assertCount(1, $posts);
        $this->assertNotEmpty($posts[0]['media']);
    }

    public function test_hashtag_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/hashtags/sunset')->assertStatus(401);
        $this->getJson('/api/v1/hashtags/search?query=mo')->assertStatus(401);
    }
}
