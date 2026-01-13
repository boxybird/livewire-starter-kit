<?php

/**
 * Events Preset
 *
 * Enforces Laravel Events & Listeners best practices:
 *
 * Events (App\Events):
 * - Past-tense naming (UserCreated, OrderPlaced)
 * - No business logic (data containers only)
 *
 * Listeners (App\Listeners):
 * - Must have handle() method
 * - Verb-noun naming (SendWelcomeEmail, NotifyAdmin)
 * - No HTTP layer access
 *
 * @see https://laravel.com/docs/events
 */
pest()->presets()->custom('events', fn (): array => [
    expect('App\Events')->toBeValidEvent(),
    expect('App\Listeners')->toBeValidListener(),
]);
