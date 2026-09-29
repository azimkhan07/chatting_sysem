<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Moderation\Enums\ReportReason;
use App\Domain\Moderation\Enums\ReportTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', Rule::enum(ReportTargetType::class)],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', Rule::enum(ReportReason::class)],
            'details' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'details' => $this->filled('details') ? trim((string) $this->input('details')) : null,
        ]);
    }
}
