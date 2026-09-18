<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Posts\PostController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::prefix('password')->middleware('throttle:password')->group(function (): void {
        Route::post('email', [ForgotPasswordController::class, 'sendLink']);
        Route::post('reset', [ForgotPasswordController::class, 'reset']);
    });

    Route::prefix('auth')->middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::prefix('posts')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/', [PostController::class, 'index']);
        Route::post('/', [PostController::class, 'store']);
    });
});
