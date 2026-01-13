<?php

/**
 * Cruddy Preset
 *
 * Enforces "Cruddy by Design" conventions for Laravel controllers:
 * - Controllers only have the 7 RESTful actions (index, create, store, show, edit, update, destroy)
 * - No protected/private methods (keeps controllers thin)
 *
 * @see https://www.youtube.com/watch?v=MF0jFKvS4SI
 */
pest()->presets()->custom('cruddy', fn (): array => [
    expect('App\Http\Controllers')->toOnlyHaveCruddyMethods(),
]);
