<?php

/**
 * Jobs Preset
 *
 * Enforces Laravel Job best practices:
 * - Must implement ShouldQueue
 * - Must have handle() method
 * - Verb-noun naming (ProcessPayment, SendEmail)
 * - No HTTP layer access
 *
 * @see https://laravel.com/docs/queues
 */
pest()->presets()->custom('jobs', fn (): array => [
    expect('App\Jobs')->toBeValidJob(),
]);
