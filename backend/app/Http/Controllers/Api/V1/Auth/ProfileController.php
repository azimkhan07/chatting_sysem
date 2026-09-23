<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadProfileImageRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

final class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->exists('display_name') && is_string($request->input('display_name'))) {
            $user->display_name = trim($request->input('display_name'));
        }

        if ($request->exists('bio')) {
            $user->bio = $request->filled('bio') ? trim((string) $request->input('bio')) : null;
        }

        $user->save();

        return ApiResponse::success([
            'user' => (new UserResource($user->refresh()))->resolve(),
        ]);
    }

    public function uploadAvatar(UploadProfileImageRequest $request): JsonResponse
    {
        return $this->storeImage($request, 'avatar_path', 'avatars');
    }

    public function uploadCover(UploadProfileImageRequest $request): JsonResponse
    {
        return $this->storeImage($request, 'cover_path', 'covers');
    }

    private function storeImage(UploadProfileImageRequest $request, string $column, string $directory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $path = $request->file('image')?->store($directory, 'public');
        if (! is_string($path) || $path === '') {
            return ApiResponse::error('UPLOAD_FAILED', 'Could not store the image.', 500);
        }

        $previous = $user->{$column};
        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $user->forceFill([$column => $path])->save();

        return ApiResponse::success([
            'user' => (new UserResource($user->refresh()))->resolve(),
        ]);
    }
}
