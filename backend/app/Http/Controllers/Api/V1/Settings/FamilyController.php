<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domain\Auth\Models\User;
use App\Domain\Family\Exceptions\FamilyNotFoundException;
use App\Domain\Family\Models\FamilyGroup;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Family\Services\FamilyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddFamilyMemberRequest;
use App\Http\Requests\StoreFamilyRequest;
use App\Http\Requests\UpdateFamilyMemberRequest;
use App\Http\Requests\UpdateFamilyRequest;
use App\Http\Resources\FamilyResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FamilyController extends Controller
{
    public function __construct(private readonly FamilyService $families) {}

    /**
     * The signed-in member's family, or `null` when they are not in one yet.
     *
     * 200 with a null family rather than 404: "you have no family" is the
     * normal first-run state, and the client renders the create form from the
     * same response that later renders the roster.
     */
    public function show(Request $request): JsonResponse
    {
        $membership = $this->families->membershipFor($this->userId($request));

        if ($membership === null) {
            return ApiResponse::success(['family' => null]);
        }

        return ApiResponse::success([
            'family' => $this->present($membership),
        ]);
    }

    public function store(StoreFamilyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $family = $this->families->createFor($user, (string) $request->validated('name'));

        $membership = $this->families->membershipFor((int) $user->id);

        if ($membership === null) {
            throw new FamilyNotFoundException('The family could not be created.');
        }

        return ApiResponse::success(
            ['family' => $this->present($membership, $family)],
            [],
            201,
        );
    }

    public function update(UpdateFamilyRequest $request): JsonResponse
    {
        $membership = $this->membership($request);

        $family = $this->families->rename($membership, (string) $request->validated('name'));

        return ApiResponse::success([
            'family' => $this->present($membership, $family),
        ]);
    }

    public function addMember(AddFamilyMemberRequest $request): JsonResponse
    {
        $membership = $this->membership($request);

        $this->families->addMember(
            $membership,
            (string) $request->validated('username'),
            $request->role(),
        );

        // The whole family comes back, not just the new member: the roster is
        // reordered by role, and the client's copy would be wrong either way.
        return ApiResponse::success(
            ['family' => $this->present($membership)],
            [],
            201,
        );
    }

    public function updateMember(UpdateFamilyMemberRequest $request, int $member): JsonResponse
    {
        $membership = $this->membership($request);

        $this->families->changeRole(
            $membership,
            $this->targetMember($member),
            $request->role(),
        );

        return ApiResponse::success([
            'family' => $this->present($membership),
        ]);
    }

    /**
     * Removes a member. A member removing themselves is a leave; the two share
     * an endpoint because the outcome is identical and the permission check is
     * the same code path.
     */
    public function destroyMember(Request $request, int $member): JsonResponse
    {
        $membership = $this->membership($request);

        $this->families->remove($membership, $this->targetMember($member));

        $isSelf = (int) $membership->id === $member;

        // The last membership row is gone, so the viewer has no family to be
        // presented with any more.
        if ($isSelf) {
            return ApiResponse::success(['left' => true, 'family' => null]);
        }

        return ApiResponse::success([
            'removed' => true,
            'family' => $this->present($membership),
        ]);
    }

    public function leave(Request $request): JsonResponse
    {
        $membership = $this->membership($request);

        $this->families->leave($membership);

        return ApiResponse::success(['left' => true, 'family' => null]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $membership = $this->membership($request);

        $this->families->dissolve($membership);

        return ApiResponse::success(['dissolved' => true, 'family' => null]);
    }

    private function membership(Request $request): FamilyMember
    {
        $membership = $this->families->membershipFor($this->userId($request));

        if ($membership === null) {
            throw new FamilyNotFoundException('You are not part of a family yet.');
        }

        return $membership;
    }

    private function targetMember(int $memberId): FamilyMember
    {
        $member = FamilyMember::query()->with(['user', 'family'])->find($memberId);

        if ($member === null) {
            throw new FamilyNotFoundException('That member is not in your family.');
        }

        return $member;
    }

    /**
     * Hands the viewer's role to the resource on the request, so
     * `FamilyResource` and `FamilyMemberResource` can compute per-viewer
     * permissions without re-querying membership.
     */
    private function present(
        FamilyMember $membership,
        ?FamilyGroup $family = null,
    ): FamilyResource {
        $group = $family ?? $membership->family;
        $group->setRelation('members', $group->members()->with(['user', 'family'])->get());

        request()->attributes->set('family_role', $membership->role);
        request()->attributes->set('family_is_owner', $membership->isOwner());

        return new FamilyResource($group);
    }

    private function userId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();

        return (int) $user->id;
    }
}
