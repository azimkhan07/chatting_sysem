<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Contracts\AuthService as AuthServiceContract;
use App\Domain\Auth\Contracts\PasswordResetService as PasswordResetServiceContract;
use App\Domain\Auth\Repositories\EloquentAuthRepository;
use App\Domain\Auth\Services\LaravelPasswordResetService;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Contracts\PostService as PostServiceContract;
use App\Domain\Posts\Repositories\EloquentPostRepository;
use App\Domain\Social\Contracts\FollowRepository;
use App\Domain\Social\Contracts\NotificationRepository;
use App\Domain\Social\Repositories\EloquentFollowRepository;
use App\Domain\Social\Repositories\EloquentNotificationRepository;
use App\Domain\Stories\Contracts\StoryRepository;
use App\Domain\Stories\Repositories\EloquentStoryRepository;
use App\Domain\Stories\Services\StoryService;
use App\Services\AuthService;
use App\Services\PostService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceContract::class, AuthService::class);
        $this->app->bind(AuthRepository::class, EloquentAuthRepository::class);
        $this->app->bind(PostServiceContract::class, PostService::class);
        $this->app->bind(PostRepository::class, EloquentPostRepository::class);
        $this->app->bind(FollowRepository::class, EloquentFollowRepository::class);
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->bind(StoryRepository::class, EloquentStoryRepository::class);
        $this->app->bind(StoryService::class, StoryService::class);
        $this->app->bind(PasswordResetServiceContract::class, LaravelPasswordResetService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinutes(1, 60)->by($request->ip()));
        RateLimiter::for('password', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('notifications', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
    }
}
