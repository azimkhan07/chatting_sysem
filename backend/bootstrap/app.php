<?php

use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Exceptions\UsernameTakenException;
use App\Domain\Chat\Exceptions\ConversationNotFoundException;
use App\Domain\Chat\Exceptions\ConversationPermissionException;
use App\Domain\Chat\Exceptions\InvalidConversationException;
use App\Domain\Posts\Exceptions\InvalidPostMediaException;
use App\Domain\Social\Exceptions\SelfFollowException;
use App\Domain\Stories\Exceptions\StoryNotAuthorizedException;
use App\Support\ApiResponse;
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
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(static fn (Request $request): JsonResponse => ApiResponse::error(
            'UNAUTHENTICATED',
            'Authentication is required.',
            401,
        ));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(static function (InvalidCredentialsException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_CREDENTIALS', $e->getMessage(), 401);
        });

        $exceptions->render(static function (InvalidPostMediaException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_MEDIA', $e->getMessage(), 422, 'media');
        });

        $exceptions->render(static function (UsernameTakenException $e, Request $request): JsonResponse {
            return ApiResponse::error('USERNAME_TAKEN', $e->getMessage(), 409, 'username');
        });

        $exceptions->render(static function (SelfFollowException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_OPERATION', $e->getMessage(), 422);
        });

        $exceptions->render(static function (StoryNotAuthorizedException $e, Request $request): JsonResponse {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        });

        $exceptions->render(static function (ConversationNotFoundException $e, Request $request): JsonResponse {
            return ApiResponse::error('NOT_FOUND', $e->getMessage(), 404);
        });

        $exceptions->render(static function (ConversationPermissionException $e, Request $request): JsonResponse {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        });

        $exceptions->render(static function (InvalidConversationException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_OPERATION', $e->getMessage(), 422);
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
