<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use Illuminate\Support\Str;

/**
 * Mints LiveKit access tokens (JWT HS256) so the backend - not the browser -
 * holds the API secret. The client only ever sees a short-lived token for one
 * room, so it cannot join rooms it has no rights to.
 */
final class LiveKitTokenIssuer
{
    private const TTL_SECONDS = 3600;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {}

    /**
     * @param  array<string, mixed>  $videoGrants
     */
    public function issue(int $identity, string $displayName, array $videoGrants): string
    {
        $now = time();

        $header = $this->encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = $this->encode([
            'iss' => $this->apiKey,
            'sub' => $this->apiKey,
            'exp' => $now + self::TTL_SECONDS,
            'nbf' => $now - 10,
            'jti' => Str::uuid()->toString(),
            'name' => $displayName,
            'identity' => (string) $identity,
            'video' => $videoGrants,
        ]);

        $signingInput = $header.'.'.$payload;
        $signature = $this->encode(
            hash_hmac('sha256', $signingInput, $this->apiSecret, true),
        );

        return $signingInput.'.'.$signature;
    }

    public function roomGrants(string $roomName): array
    {
        return [
            'room' => $roomName,
            'roomJoin' => true,
            'canPublish' => true,
            'canSubscribe' => true,
            'canPublishData' => true,
        ];
    }

    /**
     * Base64url-encode JSON or raw bytes.
     */
    private function encode(array|string $input): string
    {
        $json = is_array($input) ? json_encode($input, JSON_THROW_ON_ERROR) : $input;

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
