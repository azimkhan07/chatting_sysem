<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Email\Models\EmailConfig;
use App\Domain\Email\Models\EmailTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEmailConfigRequest;
use App\Http\Requests\SaveEmailTemplateRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Email templates CRUD + single-row transport config. Bodies are stored as HTML
 * and also stripped to plain text so the admin editor can preview how an email
 * reads before sending.
 */
final class EmailAdminController extends Controller
{
    public function templates(): JsonResponse
    {
        return ApiResponse::success([
            'templates' => EmailTemplate::query()
                ->orderByDesc('updated_at')
                ->get()
                ->map(static fn (EmailTemplate $t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'subject' => $t->subject,
                    'html_body' => $t->html_body,
                    'text_body' => $t->text_body,
                    'enabled' => $t->enabled,
                    'created_at' => $t->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function createTemplate(SaveEmailTemplateRequest $request): JsonResponse
    {
        $template = EmailTemplate::query()->create($request->validated());

        return ApiResponse::success(['template' => $template->toArray()], status: 201);
    }

    public function updateTemplate(SaveEmailTemplateRequest $request, int $templateId): JsonResponse
    {
        $template = EmailTemplate::query()->find($templateId);

        if ($template === null) {
            return ApiResponse::error('NOT_FOUND', 'Template not found.', 404);
        }

        $template->update($request->validated());

        return ApiResponse::success(['template' => $template->refresh()->toArray()]);
    }

    public function deleteTemplate(int $templateId): JsonResponse
    {
        $deleted = EmailTemplate::query()->whereKey($templateId)->delete();

        if ($deleted === 0) {
            return ApiResponse::error('NOT_FOUND', 'Template not found.', 404);
        }

        return ApiResponse::success(['deleted' => true]);
    }

    public function config(): JsonResponse
    {
        $config = EmailConfig::query()->firstOrNew(['id' => 1]);

        return ApiResponse::success([
            'config' => array_merge($config->toArray(), [
                // Never echo the stored password back over the wire.
                'password' => $config->password !== null ? '••••••••' : null,
            ]),
        ]);
    }

    public function saveConfig(SaveEmailConfigRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (($data['password'] ?? null) === '••••••••' || ($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        $config = EmailConfig::query()->updateOrCreate(['id' => 1], $data);

        return ApiResponse::success(['config' => $config->refresh()->toArray()]);
    }
}