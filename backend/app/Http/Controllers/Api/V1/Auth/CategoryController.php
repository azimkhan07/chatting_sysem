<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Enums\ProfileCategory;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * The fixed list of creator/business categories for the profile editor. It is
 * served from the API so the client never hard-codes the catalogue and the
 * backend stays the single source of truth.
 */
final class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(data: [
            'categories' => collect(ProfileCategory::cases())
                ->map(static fn (ProfileCategory $category): array => [
                    'key' => $category->value,
                    'label' => $category->label(),
                ])
                ->all(),
        ]);
    }
}
