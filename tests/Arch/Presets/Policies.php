<?php

/**
 * Policies Preset
 *
 * Enforces Laravel Policy best practices:
 * - Must end with "Policy" suffix
 * - Only authorization methods allowed (viewAny, view, create, update, delete, restore, forceDelete, before)
 *
 * @see https://laravel.com/docs/authorization#creating-policies
 */
pest()->presets()->custom('policies', fn (): array => [
    expect('App\Policies')->toBeValidPolicy(),
]);
