<?php

declare(strict_types=1);

namespace App\Domain\Settings\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Settings\Models\UserSettings;

/**
 * Privacy and notification preferences.
 *
 * Rows are created on first read rather than at registration, so a user who
 * never opens Settings still gets the documented defaults the moment anything
 * asks - and a column added later takes effect for everyone who has not
 * explicitly chosen a value.
 */
final class SettingsService
{
    public function for(User $user): UserSettings
    {
        // Created through the relation so the foreign key comes from the
        // relationship rather than mass assignment.
        /** @var UserSettings $settings */
        $settings = $user->settings()->firstOrCreate([], $this->defaults());

        return $settings;
    }

    /**
     * @param  array<string, bool>  $values
     */
    public function update(User $user, array $values): UserSettings
    {
        $settings = $this->for($user);

        if ($values !== []) {
            $settings->fill($values)->save();
        }

        return $settings->refresh();
    }

    /**
     * The same grouped shape the API and the export both use, so a client never
     * has to know whether it is reading preferences or an archive of them.
     *
     * @return array<string, array<string, bool>>
     */
    public function grouped(UserSettings $settings): array
    {
        $groups = [];

        foreach (UserSettings::preferenceGroups() as $group => $columns) {
            $groups[$group] = [];

            foreach ($columns as $column) {
                $groups[$group][$column] = (bool) $settings->{$column};
            }
        }

        return $groups;
    }

    /**
     * @return array<string, bool>
     */
    private function defaults(): array
    {
        $defaults = [];

        foreach (UserSettings::preferenceGroups() as $group) {
            foreach ($group as $column) {
                $defaults[$column] = true;
            }
        }

        return $defaults;
    }
}
