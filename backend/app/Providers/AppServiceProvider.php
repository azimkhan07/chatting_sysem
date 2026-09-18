<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Contracts\AuthService as AuthServiceContract;
use App\Domain\Auth\Repositories\EloquentAuthRepository;
use App\Domain\Posts\Contracts\PostRepository;
use App\Domain\Posts\Contracts\PostService as PostServiceContract;
use App\Domain\Posts\Repositories\EloquentPostRepository;
use App\Services\AuthService;
use App\Services\PostService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Container registrations. Interfaces resolve to their real implementations,
     * so the HTTP layer only ever depends on domain contracts.
     */
    public function register(): void
    {
        $this->app->bind(AuthServiceContract::class, AuthService::class);
        $this->app->bind(AuthRepository::class, EloquentAuthRepository::class);
        $this->app->bind(PostServiceContract::class, PostService::class);
        $this->app->bind(PostRepository::class, EloquentPostRepository::class);
    }

    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinutes(1, 60)->by($request->ip()));
    }
}
