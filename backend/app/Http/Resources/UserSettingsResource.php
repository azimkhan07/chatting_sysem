<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Settings\Models\UserSettings;
use App\Domain\Settings\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Preferences, grouped exactly as the Settings screen groups them.
 *
 * @mixin UserSettings
 */
final class UserSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(SettingsService::class)->grouped($this->resource);
    }
}
