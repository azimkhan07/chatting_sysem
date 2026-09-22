<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\SubscriptionAdminController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Http\Controllers\Api\V1\Chat\ChatMemberController;
use App\Http\Controllers\Api\V1\Chat\ChatMessageController;
use App\Http\Controllers\Api\V1\Chat\ChatUnreadController;
use App\Http\Controllers\Api\V1\Chat\ConversationController;
use App\Http\Controllers\Api\V1\Hashtags\HashtagController;
use App\Http\Controllers\Api\V1\Music\SongController;
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
        Route::get('reels', [PostController::class, 'reels']);
        Route::get('explore', [PostController::class, 'explore']);

        Route::post('{post}/like', [PostInteractionController::class, 'like']);
        Route::delete('{post}/like', [PostInteractionController::class, 'unlike']);
        Route::get('{post}/comments', [PostInteractionController::class, 'comments']);
        Route::post('{post}/comments', [PostInteractionController::class, 'storeComment']);
    });

    Route::prefix('hashtags')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('search', [HashtagController::class, 'search']);
        Route::get('{name}', [HashtagController::class, 'show']);
    });

    // Audio playback proxy: public (media elements can't send auth headers),
    // rate-limited separately so it can stream without draining `throttle:api`.
    Route::prefix('songs')->middleware('throttle:audio')->group(function (): void {
        Route::match(['get', 'head'], '{song}/stream', [SongController::class, 'stream'])->whereNumber('song');
    });

    Route::prefix('songs')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/', [SongController::class, 'index']);
        Route::get('search', [SongController::class, 'searchMusic']);
        Route::post('import', [SongController::class, 'importMusic']);
        Route::get('gifs', [SongController::class, 'searchGifs']);
    });

    Route::prefix('chat')->middleware(['auth:sanctum', 'throttle:chat'])->group(function (): void {
        Route::get('unread-total', [ChatUnreadController::class, 'total']);

        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation');
        Route::patch('conversations/{conversation}', [ConversationController::class, 'update'])->whereNumber('conversation');

        Route::get('conversations/{conversation}/messages', [ChatMessageController::class, 'index'])->whereNumber('conversation');
        Route::post('conversations/{conversation}/messages', [ChatMessageController::class, 'store'])->whereNumber('conversation');
        Route::post('conversations/{conversation}/read', [ChatMessageController::class, 'read'])->whereNumber('conversation');
        Route::post('conversations/{conversation}/typing', [ChatMessageController::class, 'typing'])->whereNumber('conversation');

        Route::post('conversations/{conversation}/members', [ChatMemberController::class, 'store'])->whereNumber('conversation');
        Route::delete('conversations/{conversation}/members/{member}', [ChatMemberController::class, 'destroy'])->whereNumber(['conversation', 'member']);
    });

    Route::prefix('users')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('search', [UserController::class, 'search']);
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

    Route::prefix('subscriptions')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('tiers', [SubscriptionController::class, 'tiers']);
        Route::post('verify', [SubscriptionController::class, 'verify']);
        Route::post('checkout', [SubscriptionController::class, 'checkout']);
        Route::get('{subscription}', [SubscriptionController::class, 'show'])->whereNumber('subscription');
        Route::post('{subscription}/pay', [SubscriptionController::class, 'pay'])->whereNumber('subscription');
        Route::delete('{subscription}', [SubscriptionController::class, 'destroy'])->whereNumber('subscription');
    });

    Route::prefix('admin/subscriptions')->middleware(['auth:sanctum', 'admin', 'throttle:api'])->group(function (): void {
        Route::get('/', [SubscriptionAdminController::class, 'index']);
        Route::post('{subscription}/approve', [SubscriptionAdminController::class, 'approve'])->whereNumber('subscription');
        Route::post('{subscription}/reject', [SubscriptionAdminController::class, 'reject'])->whereNumber('subscription');
    });
});
