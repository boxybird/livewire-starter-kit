<?php

/**
 * Observers Preset
 *
 * Enforces Laravel Observer best practices:
 * - Must end with "Observer" suffix
 * - Only model event methods allowed
 * - No HTTP layer access
 *
 * @see https://laravel.com/docs/eloquent#observers
 */
pest()->presets()->custom('observers', fn (): array => [
    expect('App\Observers')->toBeValidObserver(),
]);
