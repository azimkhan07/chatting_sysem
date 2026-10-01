<?php

declare(strict_types=1);

namespace Admin\Http\Requests;

use Admin\Domain\App\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route already sits behind the `admin` middleware; this returns
        // true so the request validates the body rather than re-asking who the
        // user is, which would put the role check in two places.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::enum(ReportStatus::class),
                // A queue entry is claimed by moving it to reviewing, and
                // closed by a terminal state. Letting staff "resolve" a report
                // back into pending would make a queue that can never drain.
                Rule::notIn([ReportStatus::Pending->value]),
            ],
            'resolution' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'resolution' => $this->filled('resolution') ? trim((string) $this->input('resolution')) : null,
        ]);
    }
}
