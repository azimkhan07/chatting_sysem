<?php

use App\Domain\Auth\Exceptions\AccountDeactivatedException;
use App\Domain\Auth\Exceptions\AccountDisabledException;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Exceptions\UsernameTakenException;
use App\Domain\Billing\Exceptions\SubscriptionNotAllowedException;
use App\Domain\Chat\Exceptions\ConversationNotFoundException;
use App\Domain\Chat\Exceptions\ConversationPermissionException;
use App\Domain\Chat\Exceptions\FeatureLockedException;
use App\Domain\Chat\Exceptions\InvalidConversationException;
use App\Domain\Family\Exceptions\FamilyNotAllowedException;
use App\Domain\Family\Exceptions\FamilyNotFoundException;
use App\Domain\Family\Exceptions\FamilyPermissionException;
use App\Domain\Moderation\Exceptions\BlockedInteractionException;
use App\Domain\Moderation\Exceptions\CannotBlockException;
use App\Domain\Moderation\Exceptions\CannotReportException;
use App\Domain\Posts\Exceptions\InvalidCommentException;
use App\Domain\Posts\Exceptions\InvalidPostMediaException;
use App\Domain\Posts\Exceptions\PostNotOwnedException;
use App\Domain\Social\Exceptions\SelfFollowException;
use App\Domain\Stories\Exceptions\StoryNotAuthorizedException;
use App\Domain\Threads\Exceptions\ThreadExpiredException;
use App\Domain\Threads\Exceptions\ThreadNotAuthorizedException;
use App\Http\Middleware\EnsureChatFeature;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsAppealHandler;
use App\Http\Middleware\EnsureUserIsStaff;
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

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            // Support desk + subscription activation: admins and the support
            // role share these endpoints, user tokens still 403.
            'staff' => EnsureUserIsStaff::class,
            // Appeal decisions belong to the support team: admins view the
            // queue, only support (or super_admin) approves/rejects.
            'appeal.handler' => EnsureUserIsAppealHandler::class,
            // Applied explicitly after `auth:sanctum` in routes/api.php: a
            // suspended account must be rejected only once the token resolved to
            // a user, and group middleware always runs before route middleware.
            'active' => EnsureUserIsActive::class,
            // Subscription-gated chat capabilities, e.g. `chat.feature:chat_wallpaper`.
            'chat.feature' => EnsureChatFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(static function (InvalidCredentialsException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_CREDENTIALS', $e->getMessage(), 401);
        });

        $exceptions->render(static function (AccountDisabledException $e, Request $request): JsonResponse {
            return ApiResponse::error('ACCOUNT_DISABLED', $e->getMessage(), 403);
        });

        $exceptions->render(static function (AccountDeactivatedException $e, Request $request): JsonResponse {
            return ApiResponse::error(
                'ACCOUNT_DEACTIVATED',
                'This account is deactivated. Reactivate it to sign back in.',
                403,
            );
        });

        $exceptions->render(static function (InvalidPostMediaException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_MEDIA', $e->getMessage(), 422, 'media');
        });

        $exceptions->render(static function (InvalidCommentException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_COMMENT', $e->getMessage(), 422, 'parent_id');
        });

        $exceptions->render(static function (UsernameTakenException $e, Request $request): JsonResponse {
            return ApiResponse::error('USERNAME_TAKEN', $e->getMessage(), 409, 'username');
        });

        $exceptions->render(static function (SelfFollowException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_OPERATION', $e->getMessage(), 422);
        });

        // Moderation. Blocked writes are 403 with one shared message, because
        // the client has nothing to branch on and, more importantly, a
        // blocked person must not be able to tell which direction the block
        // runs from the error it gets back.
        $exceptions->render(static function (BlockedInteractionException $e, Request $request): JsonResponse {
            return ApiResponse::error('BLOCKED', $e->getMessage(), 403);
        });

        // Self-block and blocking a missing account are 422, not 404: the
        // person filing it is being told their own request was wrong, and
        // there is nobody to hide the account's existence from.
        $exceptions->render(static function (CannotBlockException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_OPERATION', $e->getMessage(), 422);
        });

        $exceptions->render(static function (CannotReportException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_OPERATION', $e->getMessage(), 422);
        });

        $exceptions->render(static function (PostNotOwnedException $e, Request $request): JsonResponse {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
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

        // 403 + the feature key: the client shows the crown on that exact
        // control instead of a generic "something went wrong".
        $exceptions->render(static function (FeatureLockedException $e, Request $request): JsonResponse {
            return ApiResponse::error(
                'FEATURE_LOCKED',
                $e->getMessage(),
                403,
                'feature',
                ['feature' => $e->feature->value, 'blurb' => $e->feature->blurb()],
            );
        });

        $exceptions->render(static function (SubscriptionNotAllowedException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_OPERATION', $e->getMessage(), 422);
        });

        $exceptions->render(static function (InvalidConversationException $e, Request $request): JsonResponse {
            return ApiResponse::error('INVALID_OPERATION', $e->getMessage(), 422);
        });

        $exceptions->render(static function (ThreadNotAuthorizedException $e, Request $request): JsonResponse {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        });

        $exceptions->render(static function (ThreadExpiredException $e, Request $request): JsonResponse {
            return ApiResponse::error('THREAD_EXPIRED', $e->getMessage(), 422);
        });

        // Family Center. A missing family and someone else's family share one
        // 404 on purpose, so the endpoint cannot be used to discover that a
        // given household exists.
        $exceptions->render(static function (FamilyNotFoundException $e, Request $request): JsonResponse {
            return ApiResponse::error('NOT_FOUND', $e->getMessage(), 404);
        });

        $exceptions->render(static function (FamilyPermissionException $e, Request $request): JsonResponse {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        });

        $exceptions->render(static function (FamilyNotAllowedException $e, Request $request): JsonResponse {
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
