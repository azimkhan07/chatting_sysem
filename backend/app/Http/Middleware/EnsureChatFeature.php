<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Chat\Enums\ChatFeature;
use App\Domain\Chat\Services\ChatEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level enforcement for the subscription-gated chat tier.
 *
 * The crown in the UI is only an affordance; this is what actually holds. Each
 * gated route names its feature (`->middleware('chat.feature:chat_wallpaper')`)
 * so a locked capability is unreachable even by calling the API directly.
 */
final class EnsureChatFeature
{
    public function __construct(private readonly ChatEntitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $enum = ChatFeature::tryFrom($feature);

        if ($enum === null) {
            // An unknown feature key is a routing bug, not a user error: fail
            // closed rather than silently unlocking something.
            abort(500, 'Unknown chat feature: '.$feature);
        }

        $this->entitlements->authorize($request->user(), $enum);

        return $next($request);
    }
}
