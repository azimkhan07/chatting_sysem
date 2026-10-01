<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AppealController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\CategoryController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\ReactivateAccountController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Http\Controllers\Api\V1\Chat\CallController;
use App\Http\Controllers\Api\V1\Chat\ChatDrawingController;
use App\Http\Controllers\Api\V1\Chat\ChatEntitlementController;
use App\Http\Controllers\Api\V1\Chat\ChatInviteController;
use App\Http\Controllers\Api\V1\Chat\ChatMemberController;
use App\Http\Controllers\Api\V1\Chat\ChatMessageController;
use App\Http\Controllers\Api\V1\Chat\ChatPinController;
use App\Http\Controllers\Api\V1\Chat\ChatPresenceController;
use App\Http\Controllers\Api\V1\Chat\ChatReactionController;
use App\Http\Controllers\Api\V1\Chat\ChatUnreadController;
use App\Http\Controllers\Api\V1\Chat\ConversationController;
use App\Http\Controllers\Api\V1\Hashtags\HashtagController;
use App\Http\Controllers\Api\V1\Me\ArchiveController;
use App\Http\Controllers\Api\V1\Me\SavedController;
use App\Http\Controllers\Api\V1\Moderation\BlockController;
use App\Http\Controllers\Api\V1\Moderation\ReportController;
use App\Http\Controllers\Api\V1\Music\SongController;
use App\Http\Controllers\Api\V1\Posts\PostController;
use App\Http\Controllers\Api\V1\Posts\PostInteractionController;
use App\Http\Controllers\Api\V1\Settings\AccountController;
use App\Http\Controllers\Api\V1\Settings\FamilyController;
use App\Http\Controllers\Api\V1\Settings\SessionController;
use App\Http\Controllers\Api\V1\Settings\SettingsController;
use App\Http\Controllers\Api\V1\Story\StoryController;
use App\Http\Controllers\Api\V1\Threads\ThreadController;
use App\Http\Controllers\Api\V1\User\NotificationController;
use App\Http\Controllers\Api\V1\Users\UserController;
use App\Http\Controllers\Api\V1\Users\UserDiscoveryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        // Unauthenticated by necessity: deactivation revoked every token.
        Route::post('reactivate', ReactivateAccountController::class);
        // Unauthenticated by necessity: suspension revoked every token.
        Route::post('appeal', AppealController::class);
    });

    Route::prefix('password')->middleware('throttle:password')->group(function (): void {
        Route::post('email', [ForgotPasswordController::class, 'sendLink']);
        Route::post('reset', [ForgotPasswordController::class, 'reset']);
    });

    Route::prefix('auth')->middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::prefix('me')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::patch('/', [ProfileController::class, 'update']);
        Route::post('avatar', [ProfileController::class, 'uploadAvatar']);
        Route::post('cover', [ProfileController::class, 'uploadCover']);

        // Account Center
        Route::get('account', [AccountController::class, 'show']);
        Route::post('account/password', [AccountController::class, 'changePassword']);
        Route::post('account/deactivate', [AccountController::class, 'deactivate']);
        Route::get('account/export', [AccountController::class, 'export']);
        Route::delete('account', [AccountController::class, 'destroy']);

        // Security: signed-in devices
        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions', [SessionController::class, 'destroyOthers']);
        Route::delete('sessions/{session}', [SessionController::class, 'destroy'])
            ->whereNumber('session');

        // Privacy + notification preferences
        Route::get('settings', [SettingsController::class, 'show']);
        Route::patch('settings', [SettingsController::class, 'update']);

        // Family Center. `family/leave` is a distinct verb rather than a DELETE
        // on the member row, because the caller's own intent ("I am leaving")
        // should not be expressible as "remove member 7" from the client.
        Route::get('family', [FamilyController::class, 'show']);
        Route::post('family', [FamilyController::class, 'store']);
        Route::patch('family', [FamilyController::class, 'update']);
        Route::delete('family', [FamilyController::class, 'destroy']);
        Route::post('family/leave', [FamilyController::class, 'leave']);
        Route::post('family/members', [FamilyController::class, 'addMember']);
        Route::patch('family/members/{member}', [FamilyController::class, 'updateMember'])
            ->whereNumber('member');
        Route::delete('family/members/{member}', [FamilyController::class, 'destroyMember'])
            ->whereNumber('member');

        // Archive: my archived posts, and my expired stories, on a calendar.
        Route::get('archive/calendar', [ArchiveController::class, 'calendar']);
        Route::get('archive/posts', [ArchiveController::class, 'posts']);
        Route::get('archive/stories', [ArchiveController::class, 'stories']);

        // Saved: posts/stories I put aside, plus named collections.
        Route::get('saved', [SavedController::class, 'index']);
        Route::post('saved', [SavedController::class, 'store']);
        Route::delete('saved', [SavedController::class, 'destroy']);
        Route::get('saved/collections', [SavedController::class, 'index']);
        Route::post('saved/collections', [SavedController::class, 'storeCollection']);
        Route::delete('saved/collections/{collection}', [SavedController::class, 'destroyCollection'])
            ->whereNumber('collection');
        Route::get('saved/collections/{collection}/items', [SavedController::class, 'showCollection'])
            ->whereNumber('collection');
    });

    Route::prefix('posts')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('/', [PostController::class, 'index']);
        Route::post('/', [PostController::class, 'store']);
        Route::get('me', [PostController::class, 'mine']);
        Route::get('reels', [PostController::class, 'reels']);
        Route::get('explore', [PostController::class, 'explore']);
        Route::get('trending', [PostController::class, 'trending']);

        Route::post('{post}/like', [PostInteractionController::class, 'like']);
        Route::delete('{post}/like', [PostInteractionController::class, 'unlike']);
        Route::post('{post}/share', [PostInteractionController::class, 'share']);
        Route::get('{post}/comments', [PostInteractionController::class, 'comments']);
        Route::post('{post}/comments', [PostInteractionController::class, 'storeComment']);
        Route::post('{post}/archive', [ArchiveController::class, 'archivePost']);
        Route::post('{post}/unarchive', [ArchiveController::class, 'unarchivePost']);
        // Author-only, checked against the row's own user_id.
        Route::delete('{post}', [PostController::class, 'destroy'])->whereNumber('post');
    });

    Route::prefix('hashtags')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('search', [HashtagController::class, 'search'])->middleware('throttle:search');
        Route::get('{name}', [HashtagController::class, 'show']);
    });

    // Creator categories for the profile editor - public like the audio
    // proxy, so the editor can render before a session resolves.
    Route::get('categories', [CategoryController::class, 'index'])->middleware('throttle:api');

    // Audio playback proxy: public (media elements can't send auth headers),
    // rate-limited separately so it can stream without draining `throttle:api`.
    Route::prefix('songs')->middleware('throttle:audio')->group(function (): void {
        Route::match(['get', 'head'], '{song}/stream', [SongController::class, 'stream'])->whereNumber('song');
    });

    Route::prefix('songs')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('/', [SongController::class, 'index']);
        Route::get('search', [SongController::class, 'searchMusic']);
        Route::post('import', [SongController::class, 'importMusic']);
        Route::get('gifs', [SongController::class, 'searchGifs']);
    });

    Route::prefix('chat')->middleware(['auth:sanctum', 'active', 'throttle:chat'])->group(function (): void {
        Route::get('unread-total', [ChatUnreadController::class, 'total']);
        Route::post('presence', [ChatPresenceController::class, 'heartbeat']);
        Route::get('entitlements', [ChatEntitlementController::class, 'index']);

        Route::get('conversations', [ConversationController::class, 'index']);

        // Not feature-gated at the route: creating a group, or a DM with
        // someone you follow, must stay free. The entitlement is checked inside
        // startDm, only on the branch that would create a request.
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation');
        Route::patch('conversations/{conversation}', [ConversationController::class, 'update'])->whereNumber('conversation');
        Route::patch('conversations/{conversation}/personalize', [ConversationController::class, 'personalize'])
            ->middleware('chat.feature:chat_nickname')
            ->whereNumber('conversation');

        // Message requests: a DM from a non-follower waits here until the
        // recipient accepts or deletes it.
        Route::post('conversations/{conversation}/request/accept', [ConversationController::class, 'acceptRequest'])->whereNumber('conversation');
        Route::delete('conversations/{conversation}/request', [ConversationController::class, 'destroyRequest'])->whereNumber('conversation');

        Route::get('conversations/{conversation}/messages', [ChatMessageController::class, 'index'])->whereNumber('conversation');
        Route::post('conversations/{conversation}/messages', [ChatMessageController::class, 'store'])->whereNumber('conversation');
        Route::delete('conversations/{conversation}/messages/{message}', [ChatMessageController::class, 'destroy'])->whereNumber(['conversation', 'message']);
        Route::get('conversations/{conversation}/pins', [ChatPinController::class, 'index'])->whereNumber('conversation');
        Route::post('conversations/{conversation}/messages/{message}/pin', [ChatPinController::class, 'store'])->whereNumber(['conversation', 'message']);
        Route::delete('conversations/{conversation}/messages/{message}/pin', [ChatPinController::class, 'destroy'])->whereNumber(['conversation', 'message']);
        Route::post('conversations/{conversation}/drawings', [ChatDrawingController::class, 'store'])
            ->middleware('chat.feature:chat_drawing')
            ->whereNumber('conversation');
        Route::post('conversations/{conversation}/messages/{message}/reactions', [ChatReactionController::class, 'store'])->whereNumber(['conversation', 'message']);
        Route::delete('conversations/{conversation}/messages/{message}/reactions', [ChatReactionController::class, 'destroy'])->whereNumber(['conversation', 'message']);
        Route::post('conversations/{conversation}/read', [ChatMessageController::class, 'read'])->whereNumber('conversation');
        Route::post('conversations/{conversation}/typing', [ChatMessageController::class, 'typing'])->whereNumber('conversation');

        Route::post('conversations/{conversation}/members', [ChatMemberController::class, 'store'])->whereNumber('conversation');
        Route::delete('conversations/{conversation}/members/{member}', [ChatMemberController::class, 'destroy'])->whereNumber(['conversation', 'member']);

        Route::get('groups/{conversation}/invite', [ChatInviteController::class, 'show'])->whereNumber('conversation');
        Route::post('groups/{conversation}/invite', [ChatInviteController::class, 'store'])->whereNumber('conversation');
        Route::delete('groups/{conversation}/invite', [ChatInviteController::class, 'destroy'])->whereNumber('conversation');
        Route::post('invites/{code}/join', [ChatInviteController::class, 'join'])->where('code', '[A-Za-z0-9]+');

        Route::get('groups/{conversation}/thread', [ThreadController::class, 'show'])->whereNumber('conversation');
        Route::post('groups/{conversation}/thread', [ThreadController::class, 'start'])->whereNumber('conversation');
        Route::post('groups/{conversation}/thread/entries', [ThreadController::class, 'addEntry'])->whereNumber('conversation');
        Route::post('groups/{conversation}/thread/entries/{entry}/reactions', [ThreadController::class, 'toggleReaction'])->whereNumber(['conversation', 'entry']);

        // Voice/video calls. `start` mints the initiator's token and rings the
        // room; `answer` mints a responder's token; `reject`/`end` are signalling.
        Route::post('conversations/{conversation}/calls', [CallController::class, 'start'])->whereNumber('conversation');
        Route::post('calls/{call}/answer', [CallController::class, 'answer'])->whereNumber('call');
        Route::post('calls/{call}/reject', [CallController::class, 'reject'])->whereNumber('call');
        Route::post('calls/{call}/end', [CallController::class, 'end'])->whereNumber('call');
    });

    Route::prefix('users')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('search', [UserController::class, 'search'])->middleware('throttle:search');
        // Author-rank suggestions for the composer's "@" picker. Declared
        // before `{user}` so the literal path is never captured as a username.
        Route::get('mention-suggestions', [UserController::class, 'mentionSuggestions'])->middleware('throttle:search');
        // Static, reach-ordered discovery + opt-in phone matching. Declared
        // before `{user}` so the literal paths are never captured as a user.
        Route::get('top', [UserDiscoveryController::class, 'top'])->middleware('throttle:search');
        Route::post('match-contacts', [UserDiscoveryController::class, 'matchContacts'])->middleware('throttle:search');
        Route::get('{user}', [UserController::class, 'show']);
        Route::get('{user}/posts', [UserController::class, 'posts']);
        Route::get('{user}/followers', [UserController::class, 'followers']);
        Route::get('{user}/following', [UserController::class, 'following']);
        Route::post('{user}/follow', [UserController::class, 'follow']);
        Route::delete('{user}/follow', [UserController::class, 'unfollow']);
    });

    Route::prefix('notifications')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->middleware('throttle:notifications');
        Route::post('read', [NotificationController::class, 'markAllRead']);
    });

    Route::prefix('stories')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('/', [StoryController::class, 'index']);
        Route::post('/', [StoryController::class, 'store']);
        Route::delete('{story}', [StoryController::class, 'destroy']);
    });

    Route::prefix('subscriptions')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('tiers', [SubscriptionController::class, 'tiers']);
        Route::post('verify', [SubscriptionController::class, 'verify']);
        Route::post('checkout', [SubscriptionController::class, 'checkout']);
        Route::post('switch', [SubscriptionController::class, 'switch']);
        Route::get('active', [SubscriptionController::class, 'active']);
        Route::get('{subscription}', [SubscriptionController::class, 'show'])->whereNumber('subscription');
        Route::post('{subscription}/pay', [SubscriptionController::class, 'pay'])->whereNumber('subscription');
        Route::delete('{subscription}', [SubscriptionController::class, 'destroy'])->whereNumber('subscription');
    });

    // Blocking and reporting, for the person filing them. Block is a
    // user-facing setting they can undo, so it is a plain authenticated group;
    // the staff queue below is not.
    Route::prefix('moderation')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('report-reasons', [ReportController::class, 'reasons']);
        Route::post('reports', [ReportController::class, 'store']);

        Route::get('blocks', [BlockController::class, 'index']);
        Route::post('blocks/{userId}', [BlockController::class, 'store'])->whereNumber('userId');
        Route::delete('blocks/{userId}', [BlockController::class, 'destroy'])->whereNumber('userId');
    });
});
