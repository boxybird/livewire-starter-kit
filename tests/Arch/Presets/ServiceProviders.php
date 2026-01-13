<?php

/**
 * Service Providers Preset
 *
 * Enforces Laravel Service Provider best practices:
 * - Must end with "ServiceProvider" suffix
 * - Only allowed public methods (register, boot, provides, etc.)
 * - Deferred providers must have provides() method
 * - No HTTP layer dependencies
 *
 * Note: Excludes FortifyServiceProvider which uses Request in rate limiter closures
 * (valid pattern - closures execute at request time, not bootstrap).
 *
 * @see https://laravel.com/docs/providers
 */
pest()->presets()->custom('serviceProviders', fn (): array => [
    expect('App\Providers')->toBeValidServiceProvider(),
]);
