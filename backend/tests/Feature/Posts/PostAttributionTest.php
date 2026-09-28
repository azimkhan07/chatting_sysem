<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use App\Domain\Songs\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where, what it is set to, and who is in it.
 *
 * Location and song are the author's own choices and are stored as given.
 * Mentions are not: they are parsed from the body, because a client-supplied
 * mention list could disagree with the caption and tag someone who was never
 * named in it.
 */
final class PostAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_post_can_carry_a_location_and_a_song(): void
    {
        $user = User::factory()->create();
        $song = Song::query()->create([
            'name' => 'Nadiyon Paar',
            'artist' => 'Arijit Singh',
            'url' => 'https://cdn.test/nadiyon.mp3',
            'duration' => 180,
            'genre' => 'Bollywood',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', [
                'body' => 'Sunset at the fort',
                'location' => 'Agra Fort',
                'song_id' => $song->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.post.location', 'Agra Fort')
            ->assertJsonPath('data.post.song.id', $song->id)
            ->assertJsonPath('data.post.song.name', 'Nadiyon Paar');

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'location' => 'Agra Fort',
            'song_id' => $song->id,
        ]);
    }

    public function test_location_and_song_are_optional(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => 'Plain post'])
            ->assertStatus(201)
            ->assertJsonPath('data.post.location', null)
            ->assertJsonPath('data.post.song', null);
    }

    public function test_a_blank_location_is_stored_as_absent(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => 'No place', 'location' => '   '])
            ->assertStatus(201)
            ->assertJsonPath('data.post.location', null);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'body' => 'No place',
            'location' => null,
        ]);
    }

    public function test_an_unknown_song_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => 'Bad song', 'song_id' => 999999])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'song_id');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_mentioned_accounts_are_tagged_and_returned(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create(['username' => 'sara']);

        $this->withToken($author->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => 'Shot with @sara today'])
            ->assertStatus(201)
            ->assertJsonPath('data.post.tagged_users.0.id', $tagged->id)
            ->assertJsonPath('data.post.tagged_users.0.username', 'sara');

        $this->assertDatabaseHas('post_mentions', [
            'post_id' => Post::query()->latest('id')->value('id'),
            'user_id' => $tagged->id,
        ]);
    }

    public function test_the_author_is_not_tagged_by_tagging_themselves(): void
    {
        $author = User::factory()->create(['username' => 'amit']);

        $this->withToken($author->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => 'Note to self @amit'])
            ->assertStatus(201)
            ->assertJsonCount(0, 'data.post.tagged_users');
    }

    public function test_a_name_matching_no_account_is_skipped_not_fatal(): void
    {
        $author = User::factory()->create();
        $real = User::factory()->create(['username' => 'sara']);

        $this->withToken($author->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => 'with @nobody and @sara'])
            ->assertStatus(201)
            ->assertJsonCount(1, 'data.post.tagged_users')
            ->assertJsonPath('data.post.tagged_users.0.username', 'sara');

        $this->assertDatabaseCount('post_mentions', 1);
        $this->assertNotNull($real->id);
    }

    public function test_a_person_tagged_twice_is_one_tag(): void
    {
        $author = User::factory()->create();
        User::factory()->create(['username' => 'sara']);

        $this->withToken($author->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => '@sara @sara @SARA'])
            ->assertStatus(201)
            ->assertJsonCount(1, 'data.post.tagged_users');

        $this->assertDatabaseCount('post_mentions', 1);
    }

    public function test_removing_the_post_removes_its_tags(): void
    {
        $author = User::factory()->create();
        $tagged = User::factory()->create(['username' => 'sara']);

        $response = $this->withToken($author->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => 'with @sara'])
            ->assertStatus(201);

        $postId = (int) $response->json('data.post.id');
        $this->assertDatabaseHas('post_mentions', ['post_id' => $postId, 'user_id' => $tagged->id]);

        // `forceDelete` rather than the soft delete: posts are soft deleted, and
        // a soft delete leaves the row in place, so the cascade is only
        // observable when the row actually goes away.
        Post::query()->withTrashed()->findOrFail($postId)->forceDelete();

        $this->assertDatabaseMissing('post_mentions', ['post_id' => $postId]);
    }

    public function test_a_post_with_no_mentions_returns_an_empty_list(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => 'nobody named'])
            ->assertStatus(201)
            ->assertJsonCount(0, 'data.post.tagged_users');
    }

    public function test_hashtags_and_mentions_coexist(): void
    {
        $author = User::factory()->create();
        User::factory()->create(['username' => 'sara']);

        $this->withToken($author->createToken('test')->plainTextToken)
            ->postJson('/api/v1/posts', ['body' => '#agra with @sara'])
            ->assertStatus(201)
            ->assertJsonPath('data.post.hashtags.0', 'agra')
            ->assertJsonPath('data.post.tagged_users.0.username', 'sara');
    }
}
