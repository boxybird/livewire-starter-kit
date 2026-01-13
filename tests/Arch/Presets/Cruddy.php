<?php

/**
 * Cruddy Preset
 *
 * Enforces "Cruddy by Design" conventions for Laravel controllers:
 * - Controllers only have the 7 RESTful actions
 * - No protected/private methods
 * - Mutation methods (store, update, destroy) use Action pattern
 *
 * @see https://www.youtube.com/watch?v=MF0jFKvS4SI
 */
pest()->presets()->custom('cruddy', fn (): array => [
    expect('App\Http\Controllers')->toOnlyHaveCruddyMethods(),
    expect('App\Http\Controllers')->toUseActionsInMutationMethods(),
]);
