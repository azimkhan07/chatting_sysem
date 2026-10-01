<?php

declare(strict_types=1);

use Admin\Http\Controllers\Api\V1\Admin\DashboardAdminController;
use Admin\Http\Controllers\Api\V1\Admin\EmailAdminController;
use Admin\Http\Controllers\Api\V1\Admin\GatewayAdminController;
use Admin\Http\Controllers\Api\V1\Admin\PlanAdminController;
use Admin\Http\Controllers\Api\V1\Admin\ReportAdminController;
use Admin\Http\Controllers\Api\V1\Admin\StaffAuthController;
use Admin\Http\Controllers\Api\V1\Admin\StaffManagementController;
use Admin\Http\Controllers\Api\V1\Admin\StaffSessionController;
use Admin\Http\Controllers\Api\V1\Admin\SubscriptionAdminController;
use Admin\Http\Controllers\Api\V1\Admin\SupportAdminController;
use Admin\Http\Controllers\Api\V1\Admin\UserAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Console API
|--------------------------------------------------------------------------
|
| Every route here is a console route, and the paths are unchanged from the
| console frontend's existing `/api/v1/admin/...` calls. Keeping them identical
| means the split was a deployment change, not a client rewrite — only the
| host moves.
|
| There is no user-facing surface in this file. An app token cannot authenticate
| against the `admin:staff` guard because app users are not modelled here, so
| there is no version of this API reachable by a member of the public app.
|
| Guards, and how they compose:
|   staff        - any console role. The everyday read surface: dashboard,
|                  users, the support desk, appeals, subscriptions, reports.
|   operations   - support, admin, super_admin. Writes. A moderator passes
|                  `staff` everywhere and fails here, which is what makes the
|                  console read-only for it.
|   admin        - admin and super_admin. The team roster and the
|                  configuration screens (plan matrix, email transport, email
|                  templates, payment gateways).
|   appeal.handler - support or super_admin. Approving an appeal unsuspends a
|                  real account, so it is not a duty admins hand out.
|
| Two deliberate exceptions to "moderator reads everything", both on the
| `admin` side rather than the `operations` one. A moderator does not get the
| team roster, because seeing who the admins are is not moderation work. Nor
| does it get the configuration screens, which name the SMTP host, port and
| username and the gateway merchant id and endpoint. Writes were already closed
| to a moderator there, and a read that returns the same screen minus the
| ability to save it is not a meaningfully smaller disclosure. If a deployment
| genuinely wants a moderator to inspect mail and gateway settings, that is a
| deliberate change to the `admin` middleware, not an accident of ordering.
|
*/

Route::prefix('v1')->group(function (): void {
    // Console auth. Login is the one route that cannot be behind a guard it
    // needs to obtain, so it opts out of the group below and carries its own
    // throttle instead.
    Route::prefix('admin/auth')->group(function (): void {
        Route::post('login', [StaffAuthController::class, 'login'])->middleware('throttle:staff-auth');
    });

    Route::prefix('admin')->middleware(['auth:admin:staff', 'staff', 'throttle:api'])->group(function (): void {
        // Session lifecycle, plus a staff token cap enforced in StaffSessionManager.
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [StaffAuthController::class, 'me']);
            Route::post('logout', [StaffAuthController::class, 'logout']);
            Route::post('password', [StaffAuthController::class, 'changePassword']);
        });

        // Dashboard.
        Route::get('dashboard/stats', [DashboardAdminController::class, 'stats']);

        // Users.
        Route::get('users', [UserAdminController::class, 'index']);

        // The country catalogue is reference data, not configuration: it is the
        // name/code list every screen needs to offer a country picker, and the
        // Users filter is an everyday read that support and moderator both use.
        // Gating it on `admin` left those two roles with an empty dropdown on a
        // page they are supposed to be able to work - a filter that cannot be
        // opened is a broken filter, not a withheld secret. There is nothing
        // commercial in this payload: codes, names, and the currency each uses.
        Route::get('countries', [PlanAdminController::class, 'countries']);

        // Plan pricing and features. This is configuration, so it is gated on
        // `admin` and not merely `staff`: a support agent and a moderator have
        // no reason to see the commercial plan matrix, and a read is the only
        // thing a moderator is allowed, which makes a read the whole of what
        // this data is to them. `plans/{plan}/countries` is priced, configured
        // data and stays behind the gate even though the catalogue above does not.
        Route::middleware('admin')->group(function (): void {
            Route::get('features', [PlanAdminController::class, 'features']);
            Route::get('plans/{plan}/countries', [PlanAdminController::class, 'countriesForPlan'])
                ->whereIn('plan', ['simple', 'standard', 'premium']);
            Route::post('plans/pricing', [PlanAdminController::class, 'save'])->middleware('operations');
        });

        // Support desk. Replies are inline in the ticket, and a reply marks the
        // ticket replied rather than closed — closing is a separate decision.
        // This is the support role's own desk, so it is read by any staff but
        // written by an operator.
        Route::get('support/agents', [SupportAdminController::class, 'agents']);
        Route::get('support/tickets', [SupportAdminController::class, 'index']);
        Route::post('support/tickets/{ticket}/reply', [SupportAdminController::class, 'reply'])
            ->whereNumber('ticket')
            ->middleware('operations');

        // Suspension appeals.
        Route::get('appeals', [SupportAdminController::class, 'appeals']);
        Route::post('appeals/{appeal}/resolve', [SupportAdminController::class, 'resolveAppeal'])
            ->whereNumber('appeal')
            ->middleware('appeal.handler');

        // Email templates and transport config. Configuration, so `admin` on the
        // read as well as the write: the transport screen names the SMTP host,
        // the port and the username, and the gateway screen below names the
        // merchant id and endpoint. Secrets are masked in the response, but a
        // support agent has no need of the topology around them, and the write
        // routes were already closed to it, so leaving the read open would mean
        // the only thing the gate protected was the ability to change a value
        // that could just as well be read and screenshotted.
        Route::middleware('admin')->group(function (): void {
            Route::get('email/templates', [EmailAdminController::class, 'templates']);
            Route::post('email/templates', [EmailAdminController::class, 'createTemplate'])->middleware('operations');
            Route::patch('email/templates/{template}', [EmailAdminController::class, 'updateTemplate'])
                ->whereNumber('template')
                ->middleware('operations');
            Route::delete('email/templates/{template}', [EmailAdminController::class, 'deleteTemplate'])
                ->whereNumber('template')
                ->middleware('operations');

            Route::get('email/config', [EmailAdminController::class, 'config']);
            Route::put('email/config', [EmailAdminController::class, 'saveConfig'])->middleware('operations');

            // Payment gateways.
            Route::get('gateways', [GatewayAdminController::class, 'index']);
            Route::post('gateways', [GatewayAdminController::class, 'save'])->middleware('operations');
        });

        // Subscription review queue. Support owns activation; admins too.
        Route::prefix('subscriptions')->group(function (): void {
            Route::get('stats', [SubscriptionAdminController::class, 'stats']);
            Route::get('/', [SubscriptionAdminController::class, 'index']);
            Route::post('{subscription}/approve', [SubscriptionAdminController::class, 'approve'])
                ->whereNumber('subscription')
                ->middleware('operations');
            Route::post('{subscription}/reject', [SubscriptionAdminController::class, 'reject'])
                ->whereNumber('subscription')
                ->middleware('operations');
        });

        // The report queue. It names the accounts that reported other accounts,
        // which is why this endpoint was never reachable from the app.
        Route::prefix('reports')->group(function (): void {
            Route::get('/', [ReportAdminController::class, 'index']);
            Route::patch('{report}', [ReportAdminController::class, 'update'])
                ->whereNumber('report')
                ->middleware('operations');
        });

        // Team. An admin can hire support; only a super_admin can mint another
        // admin or super_admin, which the controller enforces on the role being
        // granted rather than on who is asking.
        Route::prefix('staff')->middleware('admin')->group(function (): void {
            Route::get('/', [StaffManagementController::class, 'index']);
            Route::post('/', [StaffManagementController::class, 'store'])->middleware('operations');
            Route::delete('{userId}', [StaffManagementController::class, 'destroy'])
                ->whereNumber('userId')
                ->middleware('operations');
        });

        // Who is signed in, from where, on what. Super admin only and not part
        // of the `admin` group above: an admin can manage the team, but this is
        // the credential inventory, and it is the console's most sensitive read.
        // Kept outside the `staff` prefix on purpose so a future
        // `/admin/staff/{id}` route cannot shadow it.
        Route::prefix('sessions')->middleware('super_admin')->group(function (): void {
            Route::get('staff', [StaffSessionController::class, 'index']);
        });
    });
});
