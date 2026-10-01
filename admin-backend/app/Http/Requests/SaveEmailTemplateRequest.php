<?php

declare(strict_types=1);

namespace Admin\Http\Requests;

use Admin\Domain\App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The title is the template's identity for staff: it is what the
            // picker shows and what someone says over chat ("edit the Welcome
            // one"). A duplicate is checked here rather than left to the
            // database so the clash comes back as a field error next to the
            // title box. Letting the unique index do it produced a 500, which
            // reads as "the console is broken" rather than "pick another name".
            'title' => [
                'required',
                'string',
                'max:100',
                Rule::unique(EmailTemplate::class)->ignore($this->currentTemplateId()),
            ],
            'subject' => ['required', 'string', 'max:180'],
            'html_body' => ['required', 'string'],
            'text_body' => ['nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * On an update the route is `email/templates/{template}`, and the record
     * being edited must not be treated as a clash with itself - otherwise
     * saving a template without renaming it always fails.
     */
    private function currentTemplateId(): ?int
    {
        $id = $this->route('template');

        return is_numeric($id) ? (int) $id : null;
    }
}
