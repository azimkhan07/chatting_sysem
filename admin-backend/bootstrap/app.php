<?php

use Admin\Domain\Admin\Exceptions\StaffNotAllowedException;
use Admin\Http\Middleware\EnsureUserIsAdmin;
use Admin\Http\Middleware\EnsureUserIsAppealHandler;
use Admin\Http\Middleware\EnsureUserIsStaff;
use Admin\Http\Middleware\EnsureUserIsSuperAdmin;
use Admin\Http\Middleware\EnsureUserCanOperate;
use Admin\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(static fn (Request $request): JsonResponse => ApiResponse::error(
            'UNAUTHENTICATED',
            'Authentication is required.',
            401,
        ));

        // Both throttle limiters key on the client IP, and behind a reverse
        // proxy every request would otherwise arrive with the proxy's address
        // and land in a single shared bucket - one person failing to type a
        // password would then lock out the whole team. Trusting the proxy is
        // what makes the limiter mean anything. Set TRUSTED_PROXIES to the
        // load balancer's address or CIDR range in production, and leave it
        // empty in local development where the client connects directly.
        $trustedProxies = array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));

        $middleware->trustProxies(
            $trustedProxies === [] ? null : $trustedProxies,
            $trustedProxies === [] ? null : (int) env('TRUSTED_PROXY_HEADERS', 1),
        );

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            // Support desk + subscription activation: admins and the support
            // role share these endpoints, user tokens still 403.
            'staff' => EnsureUserIsStaff::class,
            // Write access, as opposed to console read access: a moderator
            // passes `admin` and fails here on every mutating route.
            'operations' => EnsureUserCanOperate::class,
            // Appeal decisions belong to the support team: admins view the
            // queue, only support (or super_admin) approves/rejects.
            'appeal.handler' => EnsureUserIsAppealHandler::class,
            // The credential inventory - who is signed in, from where, on what.
            // Narrower than `admin` on purpose; see the middleware.
            'super_admin' => EnsureUserIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(static function (StaffNotAllowedException $e, Request $request): JsonResponse {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        });

        $exceptions->render(static function (ValidationException $e, Request $request): JsonResponse {
            $firstKey = array_key_first($e->errors());
            $message = $firstKey !== null ? $e->errors()[$firstKey][0] : $e->getMessage();

            return ApiResponse::error('VALIDATION_ERROR', $message, 422, $firstKey);
        });

        $exceptions->render(static function (AuthenticationException $e, Request $request): JsonResponse {
            return ApiResponse::error('UNAUTHENTICATED', 'Authentication is required.', 401);
        });

        $exceptions->render(static function (ModelNotFoundException $e, Request $request): JsonResponse {
            return ApiResponse::error('NOT_FOUND', 'The requested resource was not found.', 404);
        });

        $exceptions->render(static function (NotFoundHttpException $e, Request $request): JsonResponse {
            return ApiResponse::error('NOT_FOUND', 'The requested resource was not found.', 404);
        });
    })->create();
