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
            ->assertJsonPath('data.songs.0.name', 'Neon Nights')
            ->assertJsonPath('data.songs.0.artist', 'SoundHelix')
            ->assertJsonPath('data.songs.0.url', 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3')
            ->assertJsonCount(15, 'data.songs');
    }

    public function test_song_library_requires_authentication(): void
    {
        $this->getJson('/api/v1/songs')->assertStatus(401);
    }
}
