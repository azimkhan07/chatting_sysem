<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domain\Auth\Models\User;
use App\Domain\Settings\Services\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\UserSettingsResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'settings' => (new UserSettingsResource($this->settings->for($user)))->resolve(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $this->settings->update($user, $request->preferences());

        return ApiResponse::success([
            'settings' => (new UserSettingsResource($settings))->resolve(),
        ]);
    }
}
