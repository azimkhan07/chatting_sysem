<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Songs\Models\Song;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class SongSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Royalty-free demo tracks (SoundHelix) for the story music library.
     *
     * @var list<array{name: string, artist: string, url: string, duration: int}>
     */
    private const SONGS = [
        ['name' => 'Neon Nights', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3', 'duration' => 354],
        ['name' => 'Midnight Drive', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3', 'duration' => 306],
        ['name' => 'Golden Hour', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3', 'duration' => 420],
        ['name' => 'City Lights', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3', 'duration' => 300],
        ['name' => 'Dreamscape', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3', 'duration' => 372],
        ['name' => 'Sunset Vibes', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-7.mp3', 'duration' => 360],
        ['name' => 'Elevated', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-8.mp3', 'duration' => 312],
        ['name' => 'Flow State', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-9.mp3', 'duration' => 432],
        ['name' => 'Pulse', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-10.mp3', 'duration' => 276],
        ['name' => 'Afterglow', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-11.mp3', 'duration' => 348],
        ['name' => 'Retro Wave', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-12.mp3', 'duration' => 384],
        ['name' => 'Horizon', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-13.mp3', 'duration' => 396],
        ['name' => 'Aurora', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-14.mp3', 'duration' => 282],
        ['name' => 'Voyage', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-15.mp3', 'duration' => 330],
        ['name' => 'Symphony of the Night', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-16.mp3', 'duration' => 403],
    ];

    public function run(): void
    {
        foreach (self::SONGS as $song) {
            Song::query()->firstOrCreate([
                'name' => $song['name'],
                'artist' => $song['artist'],
            ], [
                'url' => $song['url'],
                'duration' => $song['duration'],
            ]);
        }
    }
}
