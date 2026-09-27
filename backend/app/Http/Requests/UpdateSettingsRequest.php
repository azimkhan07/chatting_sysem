<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Settings\Models\UserSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partial update of the privacy and notification preferences.
 *
 * The allowed keys are derived from the model's own column groups, so adding a
 * preference to the schema and validating it are one change, not two.
 */
final class UpdateSettingsRequest extends FormRequest
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
        $rules = [];
        $allowed = $this->allowedColumns();

        foreach (UserSettings::preferenceGroups() as $group => $columns) {
            foreach ($columns as $column) {
                // Accepted both flat and grouped, so the client may send the
                // shape its UI already has.
                $rules[$column] = ['sometimes', 'boolean'];
            }

            $rules[$group] = [
                'sometimes',
                'array',
                $this->onlyKnownKeys($columns),
            ];
        }

        $rules['preferences'] = [
            'sometimes',
            'array',
            $this->onlyKnownKeys($allowed),
        ];

        return $rules;
    }

    /**
     * Rejects an unrecognised key instead of silently dropping it, so a typo in
     * a client payload is visible rather than a preference that never saves.
     *
     * @param  list<string>  $allowed
     */
    private function onlyKnownKeys(array $allowed): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
            foreach (array_keys(is_array($value) ? $value : []) as $key) {
                if (! in_array((string) $key, $allowed, true)) {
                    $fail("Unknown preference [{$key}].");
                }
            }
        };
    }

    /**
     * @return list<string>
     */
    public function allowedColumns(): array
    {
        return array_values(array_merge(...array_values(UserSettings::preferenceGroups())));
    }

    /**
     * Flattens the accepted body shapes into one `column => bool` map.
     *
     * Only known columns are taken: an unrecognised key is dropped here, and
     * `rules()` has already rejected the request if it arrived via `preferences`.
     *
     * @return array<string, bool>
     */
    public function preferences(): array
    {
        $allowed = $this->allowedColumns();
        $flat = [];

        $collect = static function (mixed $values) use (&$flat, $allowed): void {
            if (! is_array($values)) {
                return;
            }

            foreach ($values as $key => $value) {
                if (in_array($key, $allowed, true) && is_bool($value)) {
                    $flat[$key] = $value;
                }
            }
        };

        foreach (array_keys(UserSettings::preferenceGroups()) as $group) {
            $collect($this->input($group));
        }

        $collect($this->input('preferences'));

        foreach ($this->all() as $key => $value) {
            if (in_array($key, $allowed, true) && is_bool($value)) {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }
}
