<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Music;

use App\Domain\Songs\Contracts\SongRepository;
use App\Domain\Songs\Models\Song;
use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class SongController extends Controller
{
    public function __construct(private readonly SongRepository $songs) {}

    public function index(): JsonResponse
    {
        $songs = $this->songs->all();

        return ApiResponse::success(data: ['songs' => SongResource::collection($songs)->resolve()]);
    }

    /**
     * Search the iTunes store for real songs (30-second preview clips) so the
     * story music picker offers actual Bollywood/Hollywood tracks, not just the
     * seeded royalty-free library. No API key required.
     */
    public function searchMusic(Request $request): JsonResponse
    {
        $query = (string) $request->validate(['q' => ['required', 'string', 'max:100']])['q'];

        $response = Http::timeout(10)
            ->get('https://itunes.apple.com/search', [
                'term' => $query,
                'media' => 'music',
                'limit' => 25,
                'country' => 'IN',
            ]);

        $items = $response->successful()
            ? collect($response->json('results') ?? [])
                ->map(fn (array $result): array => [
                    'name' => (string) ($result['trackName'] ?? ''),
                    'artist' => (string) ($result['artistName'] ?? ''),
                    'url' => ($preview = $result['previewUrl'] ?? null) !== null ? (string) $preview : null,
                    'duration' => 30,
                    'genre' => (string) ($result['primaryGenreName'] ?? 'Music'),
                ])
                ->filter(fn (array $item): bool => $item['name'] !== '' && $item['url'] !== null)
                ->values()
                ->all()
            : [];

        return ApiResponse::success(data: ['songs' => $items]);
    }

    /**
     * Persist a picked iTunes preview so it can be attached to a story via
     * song_id. Idempotent - upserts on name + artist.
     */
    public function importMusic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'artist' => ['required', 'string', 'max:150'],
            'url' => ['required', 'url', 'max:2048'],
            'genre' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        $song = Song::query()->updateOrCreate(
            ['name' => $data['name'], 'artist' => $data['artist']],
            ['url' => $data['url'], 'duration' => 30, 'genre' => $data['genre'] ?? null],
        );

        return ApiResponse::success(data: ['song' => (new SongResource($song))->resolve()]);
    }

    /**
     * GIF search for the story composer. Uses the Giphy API when a key is
     * configured (GIPHY_API_KEY), otherwise falls back to a zero-key search of
     * Wikimedia Commons animated GIFs so GIFs always work out of the box.
     */
    public function searchGifs(Request $request): JsonResponse
    {
        $query = (string) $request->validate(['q' => ['required', 'string', 'max:80']])['q'];

        $gifs = config('services.giphy.key')
            ? $this->giphySearch($query)
            : $this->wikimediaGifSearch($query);

        return ApiResponse::success(data: ['gifs' => $gifs]);
    }

    /**
     * @return list<array{id: int|string, url: string, preview_url: string, title: string}>
     */
    private function giphySearch(string $query): array
    {
        $response = Http::timeout(10)
            ->get('https://api.giphy.com/v1/gifs/search', [
                'api_key' => config('services.giphy.key'),
                'q' => $query,
                'limit' => 25,
                'rating' => 'g',
            ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('data') ?? [])
            ->map(fn (array $gif): ?array => [
                'id' => (string) ($gif['id'] ?? ''),
                'url' => $gif['images']['original']['url'] ?? null,
                'preview_url' => $gif['images']['fixed_width']['url'] ?? null,
                'title' => (string) ($gif['title'] ?? ''),
            ])
            ->filter(fn (?array $item): bool => $item !== null && $item['url'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int|string, url: string, preview_url: string, title: string}>
     */
    private function wikimediaGifSearch(string $query): array
    {
        $response = Http::timeout(12)
            ->withHeaders(['User-Agent' => 'amteCHAT/1.0 (https://github.com/azimkhan07/chatting_sysem)'])
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'format' => 'json',
                'generator' => 'search',
                'gsrsearch' => $query.' filetype:bitmap gif',
                'gsrnamespace' => '6',
                'gsrlimit' => '40',
                'prop' => 'imageinfo',
                'iiprop' => 'url|size',
                'iilimit' => '1',
                'iiurlwidth' => '480',
            ]);

        if (! $response->successful()) {
            return [];
        }

        $pages = $response->json('query.pages') ?? [];

        return collect($pages)
            ->map(fn (array $page): ?array => $this->wikimediaGifShape($page))
            ->filter(fn (?array $item): bool => $item !== null)
            ->take(25)
            ->values()
            ->all();
    }

    /**
     * @param  array{pageid?: int, title?: string, imageinfo?: list<array{thumburl?: string, url?: string, thumbwidth?: int, thumbheight?: int, width?: int, height?: int}>}  $page
     * @return array{id: int|string, url: string, preview_url: string, title: string}|null
     */
    private function wikimediaGifShape(array $page): ?array
    {
        $info = $page['imageinfo'][0] ?? null;
        $url = $info['thumburl'] ?? $info['url'] ?? null;
        $width = (int) ($info['thumbwidth'] ?? $info['width'] ?? 0);
        $height = (int) ($info['thumbheight'] ?? $info['height'] ?? 0);

        // Commons thumbnails carry a `?utm_...` suffix - judge the file type on
        // the clean path only.
        $cleanUrl = $url !== null ? explode('?', $url, 2)[0] : '';

        if ($cleanUrl === '' || ! str_ends_with(strtolower($cleanUrl), '.gif')) {
            return null;
        }

        // Commons hosts icons, seals and tiny banners too - keep reasonable
        // story-sized animation frames only.
        if ($width < 96 || $width > 1200 || $height < 96 || $height > 1200) {
            return null;
        }

        return [
            'id' => (int) ($page['pageid'] ?? 0),
            'url' => $url,
            'preview_url' => $url,
            'title' => (string) ($page['title'] ?? ''),
        ];
    }
}
