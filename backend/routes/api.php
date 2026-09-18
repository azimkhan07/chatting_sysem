<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Posts\PostController;
use App\Http\Controllers\Api\V1\Posts\PostInteractionController;
use App\Http\Controllers\Api\V1\Story\StoryController;
use App\Http\Controllers\Api\V1\User\NotificationController;
use App\Http\Controllers\Api\V1\Users\UserController;
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
        Route::get('me', [PostController::class, 'mine']);

        Route::post('{post}/like', [PostInteractionController::class, 'like']);
        Route::delete('{post}/like', [PostInteractionController::class, 'unlike']);
        Route::get('{post}/comments', [PostInteractionController::class, 'comments']);
        Route::post('{post}/comments', [PostInteractionController::class, 'storeComment']);
    });

    Route::prefix('users')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('{user}', [UserController::class, 'show']);
        Route::get('{user}/posts', [UserController::class, 'posts']);
        Route::get('{user}/followers', [UserController::class, 'followers']);
        Route::get('{user}/following', [UserController::class, 'following']);
        Route::post('{user}/follow', [UserController::class, 'follow']);
        Route::delete('{user}/follow', [UserController::class, 'unfollow']);
    });

    Route::prefix('notifications')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->middleware('throttle:notifications');
        Route::post('read', [NotificationController::class, 'markAllRead']);
    });

    Route::prefix('stories')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/', [StoryController::class, 'index']);
        Route::post('/', [StoryController::class, 'store']);
        Route::delete('{story}', [StoryController::class, 'destroy']);
    });
});
