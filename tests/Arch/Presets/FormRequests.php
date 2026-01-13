<?php

/**
 * Form Requests Preset
 *
 * Enforces Form Request best practices:
 * - Naming: Store{Resource}Request or Update{Resource}Request
 * - Required: authorize() and rules() methods
 * - No helper methods or business logic
 *
 * @see https://laravel.com/docs/validation#form-request-validation
 */
pest()->presets()->custom('formRequests', fn (): array => [
    expect('App\Http\Requests')->toBeValidFormRequest(),
]);
