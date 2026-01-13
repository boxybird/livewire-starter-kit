<?php

/**
 * Models Preset
 *
 * Enforces Eloquent Model best practices:
 * - No HTTP layer access (Request, Session, Cookie)
 * - Must use $fillable (not $guarded)
 * - No direct service/container calls
 *
 * @see https://laravel.com/docs/eloquent
 */
pest()->presets()->custom('models', fn (): array => [
    expect('App\Models')->toBeValidModel(),
]);
