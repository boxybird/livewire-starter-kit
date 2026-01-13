<?php

/**
 * No Volt Preset
 *
 * Enforces that Volt single-file components are not used.
 * This project uses class-based Livewire components only for
 * better architectural enforcement and IDE support.
 *
 * @see https://livewire.laravel.com/docs/components
 */
pest()->presets()->custom('noVolt', fn (): array => [
    expect('App')->toNotUseVolt(),
]);
