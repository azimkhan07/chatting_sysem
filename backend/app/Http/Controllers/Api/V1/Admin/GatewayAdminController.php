<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Billing\Models\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveGatewayRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payment gateway credentials CRUD. This is the "config via admin, no code
 * changes per gateway" surface the product asked for.
 */
final class GatewayAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'gateways' => PaymentGateway::query()
                ->orderByDesc('enabled')
                ->orderBy('name')
                ->get()
                ->map(static fn (PaymentGateway $g) => array_merge($g->toArray(), [
                    // Secrets are read-only post-save: only the masked flag returns.
                    'secret' => $g->secret !== null ? '••••••••' : null,
                ])),
        ]);
    }

    public function save(SaveGatewayRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (($data['secret'] ?? null) === '••••••••' || ($data['secret'] ?? null) === null) {
            unset($data['secret']);
        }

        $gateway = PaymentGateway::query()->updateOrCreate(
            ['name' => $data['name']],
            $data,
        );

        return ApiResponse::success(['gateway' => $gateway->refresh()->toArray()], status: 201);
    }
}