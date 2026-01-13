<?php

/**
 * Resources Preset
 *
 * Enforces Laravel API Resource best practices:
 * - Must end with "Resource" or "Collection" suffix
 * - Must have toArray() method
 * - Only allowed public methods (toArray, with, additional, etc.)
 * - No database queries
 *
 * @see https://laravel.com/docs/eloquent-resources
 */
pest()->presets()->custom('resources', fn (): array => [
    expect('App\Http\Resources')->toBeValidResource(),
]);
