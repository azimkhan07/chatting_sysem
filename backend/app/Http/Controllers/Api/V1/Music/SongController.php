<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Music;

use App\Domain\Songs\Contracts\SongRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SongController extends Controller
{
    public function __construct(private readonly SongRepository $songs) {}

    public function index(): JsonResponse
    {
        $songs = $this->songs->all();

        return ApiResponse::success(data: ['songs' => SongResource::collection($songs)->resolve()]);
    }
}
