<?php

declare(strict_types=1);

namespace Admin\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiResponse
{
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta + ['request_id' => self::requestId()],
            'errors' => [],
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $details  machine-readable extras for the
     *                                         client, e.g. the locked feature key
     */
    public static function error(string $code, string $message, int $status, ?string $field = null, array $details = []): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($field !== null) {
            $error['field'] = $field;
        }

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json([
            'data' => null,
            'meta' => ['request_id' => self::requestId()],
            'errors' => [$error],
        ], $status);
    }

    private static function requestId(): string
    {
        $header = request()->header('X-Request-Id');

        return is_string($header) && $header !== '' ? $header : Str::uuid()->toString();
    }
}
