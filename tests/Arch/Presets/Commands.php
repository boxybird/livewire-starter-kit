<?php

/**
 * Commands Preset
 *
 * Enforces Laravel Console Command best practices:
 * - Must have $signature property
 * - Must have handle() or __invoke() method
 * - Verb-noun naming (SendEmails, ProcessData)
 *
 * @see https://laravel.com/docs/artisan
 */
pest()->presets()->custom('commands', fn (): array => [
    expect('App\Console\Commands')->toBeValidCommand(),
]);
