<?php

/**
 * Livewire Preset
 *
 * Enforces Livewire component best practices:
 * - Use method injection instead of service location
 *
 * @see https://livewire.laravel.com/docs/components
 */
pest()->presets()->custom('livewire', fn (): array => [
    expect('App\\Livewire')->toUseDependencyInjection(),
]);
