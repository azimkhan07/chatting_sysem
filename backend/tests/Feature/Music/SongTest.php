<?php

declare(strict_types=1);

namespace Tests\Feature\Music;

use App\Domain\Auth\Models\User;
use Database\Seeders\SongSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SongTest extends TestCase
{
    use RefreshDatabase;

    public function test_song_library_returns_seeded_songs(): void
    {
        $user = User::factory()->create();
        $this->seed(SongSeeder::class);

        $this->actingAs($user)
            ->getJson('/api/v1/songs')
            ->assertOk()
            ->assertJsonPath('data.songs.0.name', 'Heroic Age')
            ->assertJsonPath('data.songs.0.artist', 'Kevin MacLeod')
            ->assertJsonPath('data.songs.0.genre', 'Hollywood OST')
            ->assertJsonPath('data.songs.0.url', 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Heroic%20Age.mp3')
            ->assertJsonCount(43, 'data.songs');
    }

    public function test_song_library_includes_worldwide_genres(): void
    {
        $user = User::factory()->create();
        $this->seed(SongSeeder::class);

        $genres = ['Hollywood OST', 'Bollywood Desi', 'EDM Party', 'Chill Lo-fi', 'Workout Rock', 'Fun Playful', 'Acoustic Jazz'];

        $songs = $this->actingAs($user)
            ->getJson('/api/v1/songs')
            ->assertOk()
            ->json('data.songs');

        foreach ($genres as $genre) {
            $this->assertTrue(collect($songs)->contains('genre', $genre), "Missing songs with genre {$genre}");
        }
    }

    public function test_song_library_requires_authentication(): void
    {
        $this->getJson('/api/v1/songs')->assertStatus(401);
    }
}
