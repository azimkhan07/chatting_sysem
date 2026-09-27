<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One signed-in device.
 *
 * The token value is never exposed - only the Sanctum row id, which is what
 * the revoke endpoint needs.
 *
 * @mixin PersonalAccessToken
 */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'name' => (string) $this->resource->name,
            'device' => $this->device(),
            'last_used_at' => $this->resource->last_used_at?->toIso8601String(),
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'is_current' => $this->resource->id === $request->user()?->currentTokenId(),
        ];
    }

    /**
     * Best-effort client description.
     *
     * User-agent parsing is deliberately shallow: it only has to be good enough
     * for a person to recognise "that is my phone", and a wrong guess is
     * harmless.
     */
    private function device(): string
    {
        $agent = (string) ($this->resource->name ?? '');

        foreach ([
            'iPhone' => 'iPhone',
            'iPad' => 'iPad',
            'Android' => 'Android',
            'Windows' => 'Windows',
            'Macintosh' => 'Mac',
            'Linux' => 'Linux',
        ] as $needle => $label) {
            if (str_contains($agent, $needle)) {
                return $label;
            }
        }

        return 'Unknown device';
    }
}
