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
     * Worldwide royalty-free library (CC-BY 4.0 / free-to-use) for the story
     * music picker, grouped by mood and region so it feels like a Bollywood +
     * Hollywood + International song library.
     *
     * @var list<array{name: string, artist: string, url: string, duration: int, genre: string}>
     */
    private const SONGS = [
        // ── Hollywood / cinematic OST ──────────────────────────────────────────────
        ['name' => 'Heroic Age', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Heroic%20Age.mp3', 'duration' => 195, 'genre' => 'Hollywood OST'],
        ['name' => 'Rynos Theme', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Rynos%20Theme.mp3', 'duration' => 222, 'genre' => 'Hollywood OST'],
        ['name' => 'Impact Moderato', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Impact%20Moderato.mp3', 'duration' => 184, 'genre' => 'Hollywood OST'],
        ['name' => 'Danse Macabre', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Danse%20Macabre.mp3', 'duration' => 188, 'genre' => 'Hollywood OST'],
        ['name' => 'The Curtain Rises', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/The%20Curtain%20Rises.mp3', 'duration' => 237, 'genre' => 'Hollywood OST'],
        ['name' => 'Prelude and Action', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Prelude%20and%20Action.mp3', 'duration' => 197, 'genre' => 'Hollywood OST'],
        ['name' => 'Killers', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Killers.mp3', 'duration' => 204, 'genre' => 'Hollywood OST'],
        ['name' => 'Volatile Reaction', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Volatile%20Reaction.mp3', 'duration' => 210, 'genre' => 'Hollywood OST'],

        // ── Bollywood / Desi fusion ────────────────────────────────────────────────
        ['name' => 'Opium', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Opium.mp3', 'duration' => 214, 'genre' => 'Bollywood Desi'],
        ['name' => 'Sardana', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Sardana.mp3', 'duration' => 172, 'genre' => 'Bollywood Desi'],
        ['name' => 'Desert City', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Desert%20City.mp3', 'duration' => 194, 'genre' => 'Bollywood Desi'],
        ['name' => 'Folk Round', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Folk%20Round.mp3', 'duration' => 226, 'genre' => 'Bollywood Desi'],
        ['name' => 'Past Sadness', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Past%20Sadness.mp3', 'duration' => 186, 'genre' => 'Bollywood Desi'],

        // ── EDM / party ────────────────────────────────────────────────────────────
        ['name' => 'Neon Nights', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3', 'duration' => 354, 'genre' => 'EDM Party'],
        ['name' => 'Midnight Drive', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3', 'duration' => 306, 'genre' => 'EDM Party'],
        ['name' => 'Golden Hour', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3', 'duration' => 420, 'genre' => 'EDM Party'],
        ['name' => 'City Lights', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3', 'duration' => 300, 'genre' => 'EDM Party'],
        ['name' => 'Dreamscape', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3', 'duration' => 372, 'genre' => 'EDM Party'],
        ['name' => 'Pulse', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-10.mp3', 'duration' => 276, 'genre' => 'EDM Party'],
        ['name' => 'Afterglow', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-11.mp3', 'duration' => 348, 'genre' => 'EDM Party'],
        ['name' => 'Neon Laser Horizon', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Neon%20Laser%20Horizon.mp3', 'duration' => 228, 'genre' => 'EDM Party'],
        ['name' => 'Digital Lemonade', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Digital%20Lemonade.mp3', 'duration' => 171, 'genre' => 'EDM Party'],

        // ── Chill / lo-fi ──────────────────────────────────────────────────────────
        ['name' => 'Carefree', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Carefree.mp3', 'duration' => 247, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Ice Flow', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Ice%20Flow.mp3', 'duration' => 247, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Local Forecast — Elevator', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Local%20Forecast%20-%20Elevator.mp3', 'duration' => 135, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Wallpaper', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Wallpaper.mp3', 'duration' => 158, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Fluffing a Duck', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Fluffing%20a%20Duck.mp3', 'duration' => 166, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Wholesome', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Wholesome.mp3', 'duration' => 165, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Healing', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Healing.mp3', 'duration' => 217, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Dreamy Flashback', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Dreamy%20Flashback.mp3', 'duration' => 255, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Vibing Over Venus', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Vibing%20Over%20Venus.mp3', 'duration' => 182, 'genre' => 'Chill Lo-fi'],

        // ── Workout / upbeat ───────────────────────────────────────────────────────
        ['name' => 'Faster Does It', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Faster%20Does%20It.mp3', 'duration' => 136, 'genre' => 'Workout Rock'],
        ['name' => 'Sunset Vibes', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-7.mp3', 'duration' => 360, 'genre' => 'Workout Rock'],
        ['name' => 'Elevated', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-8.mp3', 'duration' => 312, 'genre' => 'Workout Rock'],
        ['name' => 'Flow State', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-9.mp3', 'duration' => 432, 'genre' => 'Workout Rock'],
        ['name' => 'Horizon', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-13.mp3', 'duration' => 396, 'genre' => 'Workout Rock'],

        // ── Fun & playful ─────────────────────────────────────────────────────────
        ['name' => 'Sneaky Snitch', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Sneaky%20Snitch.mp3', 'duration' => 232, 'genre' => 'Fun Playful'],
        ['name' => 'Monkeys Spinning Monkeys', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/Monkeys%20Spinning%20Monkeys.mp3', 'duration' => 198, 'genre' => 'Fun Playful'],
        ['name' => 'Retro Wave', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-12.mp3', 'duration' => 384, 'genre' => 'Fun Playful'],

        // ── Acoustic / jazz ────────────────────────────────────────────────────────
        ['name' => 'George Street Shuffle', 'artist' => 'Kevin MacLeod', 'url' => 'https://incompetech.com/music/royalty-free/mp3-royaltyfree/George%20Street%20Shuffle.mp3', 'duration' => 202, 'genre' => 'Acoustic Jazz'],

        // ── Additional international / ambient ────────────────────────────────────
        ['name' => 'Symphony of the Night', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-16.mp3', 'duration' => 403, 'genre' => 'Hollywood OST'],
        ['name' => 'Aurora', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-14.mp3', 'duration' => 282, 'genre' => 'Chill Lo-fi'],
        ['name' => 'Voyage', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-15.mp3', 'duration' => 330, 'genre' => 'Chill Lo-fi'],
    ];

    public function run(): void
    {
        foreach (self::SONGS as $song) {
            Song::query()->updateOrCreate(
                ['name' => $song['name'], 'artist' => $song['artist']],
                [
                    'url' => $song['url'],
                    'duration' => $song['duration'],
                    'genre' => $song['genre'],
                ],
            );
        }
    }
}
