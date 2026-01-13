<?php

/**
 * Actions Preset
 *
 * Enforces that action classes in app/Actions/ follow the single-responsibility pattern:
 * - Only one public method: handle()
 * - No protected/private helper methods
 *
 * Note: Excludes App\Actions\Fortify which uses Laravel's official Fortify conventions.
 *
 * @see https://laravelactions.com/
 */
pest()->presets()->custom('actions', fn (): array => [
    expect('App\Actions')->toBeValidAction(),
]);
