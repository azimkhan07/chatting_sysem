<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Music;

use App\Domain\Songs\Contracts\SongRepository;
use App\Domain\Songs\Models\Song;
use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Support\ApiResponse;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class SongController extends Controller
{
    public function __construct(private readonly SongRepository $songs) {}

    public function index(): JsonResponse
    {
        $songs = $this->songs->all();

        return ApiResponse::success(data: ['songs' => SongResource::collection($songs)->resolve()]);
    }

    /**
     * Search for real songs (30-second preview clips) so the story music
     * picker offers actual Bollywood/Hollywood tracks, not just the seeded
     * royalty-free library. No API key required.
     *
     * Falls back gracefully: iTunes India store first, then the US store,
     * then a fuzzy match over the local library - so a search always yields
     * something instead of a dead "No songs found" panel.
     */
    public function searchMusic(Request $request): JsonResponse
    {
        $query = (string) $request->validate(['q' => ['required', 'string', 'max:100']])['q'];

        $items = $this->itunesSearch($query, 'IN');

        if ($items === []) {
            $items = $this->itunesSearch($query, 'US');
        }

        if ($items === []) {
            $items = $this->localLibrarySearch($query);
        }

        return ApiResponse::success(data: ['songs' => $items]);
    }

    /**
     * Query the iTunes Search API for track preview clips.
     *
     * @return list<array{name: string, artist: string, url: string|null, duration: int, genre: string}>
     */
    private function itunesSearch(string $query, string $country): array
    {
        try {
            $response = Http::timeout(10)
                ->get('https://itunes.apple.com/search', [
                    'term' => $query,
                    'media' => 'music',
                    'limit' => 30,
                    'country' => $country,
                ]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('results') ?? [])
            ->map(fn (array $result): array => [
                'name' => (string) ($result['trackName'] ?? ''),
                'artist' => (string) ($result['artistName'] ?? ''),
                'url' => ($preview = $result['previewUrl'] ?? null) !== null ? (string) $preview : null,
                'duration' => 30,
                'genre' => (string) ($result['primaryGenreName'] ?? 'Music'),
            ])
            ->filter(fn (array $item): bool => $item['name'] !== '' && $item['url'] !== null)
            ->values()
            ->all();
    }

    /**
     * Fuzzy match against the seeded library so offline/slow iTunes days still
     * return usable songs for the picker.
     *
     * @return list<array{name: string, artist: string, url: string, duration: int|null, genre: string|null}>
     */
    private function localLibrarySearch(string $query): array
    {
        $needle = strtolower(trim($query));

        if ($needle === '') {
            return [];
        }

        $tokens = array_values(array_filter(
            preg_split('/[\s,_\-&+()\[\]!.]+/', $needle) ?? [],
            static fn (string $token): bool => strlen($token) > 1,
        ));

        $haystackFor = static fn (Song $song): string => strtolower(trim(
            $song->name.' '.$song->artist.' '.($song->genre ?? ''),
        ));

        return $this->songs->all()
            ->filter(static function (Song $song) use ($needle, $tokens, $haystackFor): bool {
                $haystack = $haystackFor($song);

                if (str_contains($haystack, $needle)) {
                    return true;
                }

                foreach ($tokens as $token) {
                    if (str_contains($haystack, $token)) {
                        return true;
                    }
                }

                return false;
            })
            ->take(15)
            ->map(fn (Song $song): array => [
                'name' => $song->name,
                'artist' => $song->artist,
                'url' => $song->url,
                'duration' => $song->duration,
                'genre' => $song->genre,
            ])
            ->values()
            ->all();
    }

    /**
     * Proxy a song's audio through the API so story music always plays: this
     * avoids expired/hotlink-blocked preview URLs, mixed-content, and dead
     * third-party hosts by streaming the bytes from the backend itself.
     *
     * This route is public (media elements cannot send Authorization headers)
     * and is rate-limited via the `audio` limiter.
     */
    public function stream(Song $song): StreamedResponse
    {
        $client = new Client(['timeout' => 0, 'connect_timeout' => 15]);

        try {
            $upstream = $client->get($song->url, [
                'stream' => true,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'audio/webm,audio/ogg,audio/wav,audio/*;q=0.9,*/*;q=0.8',
                ],
            ]);
        } catch (Throwable) {
            abort(502, 'The audio source is temporarily unavailable.');
        }

        $status = $upstream->getStatusCode();

        if ($status === 404 || $status === 410 || $status === 403) {
            abort(422, 'The audio source is unavailable.');
        }

        if ($status >= 400) {
            abort(502, 'The audio source returned an error.');
        }

        $contentType = (string) $upstream->getHeaderLine('Content-Type');

        return response()->stream(function () use ($upstream): void {
            $body = $upstream->getBody();

            while (! $body->eof()) {
                if (connection_aborted()) {
                    break;
                }

                echo $body->read(512 * 1024);
                flush();
            }

            $body->close();
        }, 200, [
            'Content-Type' => $contentType !== '' ? $contentType : 'audio/mpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
