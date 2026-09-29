<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Enums\AccountType;
use App\Domain\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadProfileImageRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->exists('display_name') && is_string($request->input('display_name'))) {
            $user->display_name = trim($request->input('display_name'));
        }

        if ($request->exists('bio')) {
            $user->bio = $request->filled('bio') ? trim((string) $request->input('bio')) : null;
        }

        if ($request->exists('account_type')) {
            $accountType = AccountType::from((string) $request->input('account_type'));

            // Switching back to Personal must not leave a published contact
            // block behind that a later toggle would re-expose.
            if ($accountType === AccountType::Personal) {
                $user->show_contact = false;
                $user->contact_email = null;
                $user->contact_phone = null;
                $user->category = null;
            }

            $user->account_type = $accountType;
        }

        if ($request->exists('is_private')) {
            $user->is_private = $request->boolean('is_private');

            // A private account is personal by definition: there is no
            // published contact block on a locked profile. A professional or
            // business badge plus a private profile would contradict what the
            // two account types advertise to visitors.
            if ($user->is_private) {
                $user->account_type = AccountType::Personal;
                $user->show_contact = false;
                $user->contact_email = null;
                $user->contact_phone = null;
                $user->category = null;
            }
        }

        // Only professional and business accounts can carry a category; a
        // personal account cleared it above, and a stray category on an older
        // row is dropped here on the next write.
        if ($request->exists('category')) {
            $category = $request->input('category');
            $user->category = is_string($category) && $category !== ''
                && $user->account_type->offersContact()
                    ? $category
                    : null;
        }

        if ($request->exists('contact_email')) {
            $user->contact_email = $request->filled('contact_email')
                ? mb_strtolower(trim((string) $request->input('contact_email')))
                : null;
        }

        if ($request->exists('contact_phone')) {
            $user->contact_phone = $request->filled('contact_phone')
                ? trim((string) $request->input('contact_phone'))
                : null;
        }

        if ($request->exists('show_contact')) {
            // There is nothing to show without a contact method, so a toggle
            // alone can never publish an empty block.
            $hasContact = is_string($user->contact_email) && $user->contact_email !== ''
                || is_string($user->contact_phone) && $user->contact_phone !== '';

            $user->show_contact = $request->boolean('show_contact')
                && $hasContact
                && $user->account_type->offersContact();
        }

        $user->save();

        return ApiResponse::success([
            'user' => (new UserResource($user->refresh()))->resolve(),
        ]);
    }

    public function uploadAvatar(UploadProfileImageRequest $request): JsonResponse
    {
        return $this->storeImage($request, 'avatar_path', 'avatars');
    }

    public function uploadCover(UploadProfileImageRequest $request): JsonResponse
    {
        return $this->storeImage($request, 'cover_path', 'covers');
    }

    private function storeImage(UploadProfileImageRequest $request, string $column, string $directory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $path = $request->file('image')?->store($directory, 'public');
        if (! is_string($path) || $path === '') {
            return ApiResponse::error('UPLOAD_FAILED', 'Could not store the image.', 500);
        }

        $previous = $user->{$column};
        $replaced = is_string($previous) && $previous !== '' && $previous !== $path;

        try {
            $user->forceFill([$column => $path])->save();
        } catch (Throwable $e) {
            // Never leave an orphaned file behind when the write did not land.
            Storage::disk('public')->delete($path);

            throw $e;
        }

        if ($replaced) {
            Storage::disk('public')->delete($previous);
        }

        return ApiResponse::success([
            'user' => (new UserResource($user->refresh()))->resolve(),
        ]);
    }
}
