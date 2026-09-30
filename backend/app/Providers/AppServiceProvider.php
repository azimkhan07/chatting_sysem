<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Archive\Contracts\ArchiveService as ArchiveServiceContract;
use App\Domain\Archive\Services\EloquentArchiveService;
use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Contracts\AuthService as AuthServiceContract;
use App\Domain\Auth\Contracts\PasswordResetService as PasswordResetServiceContract;
use App\Domain\Auth\Models\PersonalAccessToken as AppPersonalAccessToken;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Repositories\EloquentAuthRepository;
use App\Domain\Auth\Services\LaravelPasswordResetService;
use App\Domain\Billing\Contracts\SubscriptionRepository;
use App\Domain\Billing\Repositories\EloquentSubscriptionRepository;
use App\Domain\Chat\Contracts\CallService as CallServiceContract;
use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Contracts\ChatService as ChatServiceContract;
use App\Domain\Chat\Contracts\PresenceService as PresenceServiceContract;
use App\Domain\Chat\Repositories\EloquentChatRepository;
use App\Domain\Chat\Services\LiveKitTokenIssuer;
use App\Domain\Chat\Services\PresenceStore;
use App\Domain\Hashtags\Contracts\HashtagRepository;
use App\Domain\Hashtags\Repositories\EloquentHashtagRepository;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Contracts\PostService as PostServiceContract;
use App\Domain\Posts\Repositories\EloquentPostRepository;
use App\Domain\Saved\Contracts\SavedService as SavedServiceContract;
use App\Domain\Saved\Services\EloquentSavedService;
use App\Domain\Social\Contracts\FollowRepository;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Repositories\EloquentFollowRepository;
use App\Domain\Social\Repositories\EloquentNotificationRepository;
use App\Domain\Songs\Contracts\SongRepository;
use App\Domain\Songs\Repositories\EloquentSongRepository;
use App\Domain\Stories\Contracts\StoryRepository;
use App\Domain\Stories\Repositories\EloquentStoryRepository;
use App\Domain\Stories\Services\StoryService;
use App\Domain\Threads\Contracts\ThreadRepository;
use App\Domain\Threads\Repositories\EloquentThreadRepository;
use App\Services\AuthService;
use App\Services\CallService;
use App\Services\ChatService;
use App\Services\PostService;
use App\Services\PresenceService;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceContract::class, AuthService::class);
        $this->app->bind(AuthRepository::class, EloquentAuthRepository::class);
        $this->app->bind(PostServiceContract::class, PostService::class);
        $this->app->bind(PostRepository::class, EloquentPostRepository::class);
        $this->app->bind(HashtagRepository::class, EloquentHashtagRepository::class);
        $this->app->bind(SongRepository::class, EloquentSongRepository::class);
        $this->app->bind(FollowRepository::class, EloquentFollowRepository::class);
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->bind(StoryRepository::class, EloquentStoryRepository::class);
        $this->app->bind(StoryService::class, StoryService::class);
        $this->app->bind(ArchiveServiceContract::class, EloquentArchiveService::class);
        $this->app->bind(SavedServiceContract::class, EloquentSavedService::class);
        $this->app->bind(ChatRepository::class, EloquentChatRepository::class);
        $this->app->bind(ChatServiceContract::class, ChatService::class);
        $this->app->bind(CallServiceContract::class, CallService::class);
        $this->app->bind(LiveKitTokenIssuer::class, static fn (): LiveKitTokenIssuer => new LiveKitTokenIssuer(
            (string) config('livekit.api_key'),
            (string) config('livekit.api_secret'),
        ));
        $this->app->bind(CallService::class, static fn (): CallService => new CallService(
            app(ChatRepository::class),
            app(LiveKitTokenIssuer::class),
            (string) config('livekit.url'),
        ));
        $this->app->bind(ThreadRepository::class, EloquentThreadRepository::class);
        $this->app->bind(SubscriptionRepository::class, EloquentSubscriptionRepository::class);
        $this->app->bind(PasswordResetServiceContract::class, LaravelPasswordResetService::class);
        $this->app->bind(PresenceServiceContract::class, PresenceService::class);

        // One presence snapshot per request, so a 100+ conversation inbox reads
        // the online set exactly once.
        $this->app->singleton(PresenceStore::class);
    }

    public function boot(): void
    {
        // One shared token table for both app users and console staff. Without
        // this, staff tokens would follow the StaffUser model onto the `admin`
        // connection while Sanctum keeps looking for them on the app one.
        Sanctum::usePersonalAccessTokenModel(AppPersonalAccessToken::class);

        // Any authenticated request is proof of life: hooking the auth event
        // keeps presence correct without threading a middleware through every
        // route group. The service throttles this to one write per 30s.
        Event::listen(Authenticated::class, static function (Authenticated $event): void {
            $user = $event->user;

            if ($user instanceof User) {
                app(PresenceServiceContract::class)->touch($user);
            }
        });

        // Rate limits are keyed by account when there is one: an IP key punishes
        // every user behind a single NAT/office egress with one shared bucket.
        $byUser = static fn (Request $request): string => $request->user() !== null
            ? 'u:'.$request->user()->getAuthIdentifier()
            : 'ip:'.$request->ip();

        // A returned array of limits is enforced cumulatively by ThrottleRequests,
        // so auth is both per-minute and per-day capped.
        RateLimiter::for('auth', fn (Request $request): array => [
            Limit::perMinute(10)->by('auth:'.$request->ip()),
            Limit::perDay(30)->by('auth:'.$request->ip()),
        ]);
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinutes(1, 60)->by($byUser($request)));
        RateLimiter::for('feed', fn (Request $request): Limit => Limit::perMinutes(1, 120)->by($byUser($request)));
        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinutes(1, 60)->by($byUser($request)));
        RateLimiter::for('password', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('notifications', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('chat', function (Request $request): Limit {
            $user = $request->user();
            $subject = $user !== null ? (string) $user->id : $request->ip();

            return Limit::perMinute(300)->by($subject);
        });
        RateLimiter::for('audio', fn (Request $request): Limit => Limit::perMinutes(1, 120)->by($request->ip()));

        // A numeric segment is always an id; anything else is a username. Without
        // this, a user whose username is "7" would shadow the account with id 7.
        Route::bind('user', function (string $value): User {
            return ctype_digit($value)
                ? User::whereKey((int) $value)->firstOrFail()
                : User::where('username', $value)->firstOrFail();
        });
    }
}
