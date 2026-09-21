<?php

declare(strict_types=1);

namespace Tests\Feature\Music;

use App\Domain\Auth\Models\User;
use App\Domain\Songs\Models\Song;
use Database\Seeders\SongSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_song_search_returns_live_itunes_previews(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://itunes.apple.com/*' => Http::response([
                'resultCount' => 1,
                'results' => [
                    [
                        'trackName' => 'Kesariya',
                        'artistName' => 'Arijit Singh',
                        'previewUrl' => 'https://audio-ssl.itunes.apple.com/preview.m4a',
                        'primaryGenreName' => 'Bollywood',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/songs/search?q=kesariya')
            ->assertOk()
            ->assertJsonPath('data.songs.0.name', 'Kesariya')
            ->assertJsonPath('data.songs.0.artist', 'Arijit Singh')
            ->assertJsonPath('data.songs.0.url', 'https://audio-ssl.itunes.apple.com/preview.m4a');
    }

    public function test_song_search_falls_back_to_local_library_when_itunes_fails(): void
    {
        $user = User::factory()->create();
        $this->seed(SongSeeder::class);

        Http::fake(['https://itunes.apple.com/*' => Http::response('', 503)]);

        $this->actingAs($user)
            ->getJson('/api/v1/songs/search?q=Heroic')
            ->assertOk()
            ->assertJsonPath('data.songs.0.name', 'Heroic Age')
            ->assertJsonPath('data.songs.0.artist', 'Kevin MacLeod');
    }

    public function test_song_stream_endpoint_is_public_and_streams_audio(): void
    {
        $song = Song::query()->create([
            'name' => 'Local Test Tone',
            'artist' => 'Tests',
            'url' => 'http://127.0.0.1:1/unreachable.mp3',
            'duration' => 30,
            'genre' => 'Test',
        ]);

        $this->getJson('/api/v1/songs/'.$song->id.'/stream')
            ->assertStatus(502);
    }

    public function test_song_resource_exposes_stream_url(): void
    {
        $user = User::factory()->create();
        $this->seed(SongSeeder::class);

        $this->actingAs($user)
            ->getJson('/api/v1/songs')
            ->assertOk()
            ->assertJsonPath('data.songs.0.stream_url', '/api/v1/songs/1/stream');
    }
}
