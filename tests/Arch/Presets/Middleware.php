<?php

/**
 * Middleware Preset
 *
 * Enforces Laravel Middleware best practices:
 * - Must have handle() method
 * - Verb-based naming (EnsureUserIsAdmin, ValidateApiToken)
 *
 * @see https://laravel.com/docs/middleware
 */
pest()->presets()->custom('middleware', fn (): array => [
    expect('App\Http\Middleware')->toBeValidMiddleware(),
]);
