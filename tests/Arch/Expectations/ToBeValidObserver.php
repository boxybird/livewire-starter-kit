<?php

/**
 * Observer Expectation
 *
 * Enforces Laravel Observer best practices:
 * - Must end with "Observer" suffix
 * - Only model event methods allowed
 * - No HTTP layer access
 *
 * @see https://laravel.com/docs/eloquent#observers
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidObserver', function () {
    $namespace = $this->value;

    // Allowed model event methods
    $allowedMethods = [
        '__construct',
        'retrieved',
        'creating',
        'created',
        'updating',
        'updated',
        'saving',
        'saved',
        'deleting',
        'deleted',
        'trashed',
        'forceDeleting',
        'forceDeleted',
        'restoring',
        'restored',
        'replicating',
    ];

    // Forbidden use statements (HTTP layer)
    $forbiddenUses = [
        'Illuminate\\Http\\Request' => 'HTTP Request',
        'Illuminate\\Support\\Facades\\Session' => 'Session facade',
        'Illuminate\\Support\\Facades\\Cookie' => 'Cookie facade',
        'Illuminate\\Support\\Facades\\Request' => 'Request facade',
    ];

    // Forbidden patterns in code
    $forbiddenPatterns = [
        '/\\brequest\\s*\\(/' => 'request() helper',
        '/\\bsession\\s*\\(/' => 'session() helper',
    ];

    $objects = ObjectsRepository::getInstance()->allByNamespace($namespace);

    foreach ($objects as $object) {
        if (! isset($object->reflectionClass)) {
            continue;
        }

        $reflection = $object->reflectionClass;
        if ($reflection->isAbstract()) {
            continue;
        }
        if ($reflection->isInterface()) {
            continue;
        }
        if ($reflection->isTrait()) {
            continue;
        }

        $shortClass = class_basename($reflection->getName());
        $fileContent = file_get_contents($object->path);

        // Rule 1: Must end with "Observer"
        if (! str_ends_with($shortClass, 'Observer')) {
            $suggestedName = $shortClass.'Observer';

            $message = <<<MSG
OBSERVER VIOLATION

RULE: Observers must end with "Observer" suffix.

REASON: The Observer suffix makes model event handler classes immediately
identifiable and follows Laravel's naming convention.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

EXAMPLES:
- UserWatcher → UserObserver
- UserEvents → UserObserver
- UserHandler → UserObserver

FIX: Rename to end with Observer
php artisan make:observer {$suggestedName}

REFERENCE: https://laravel.com/docs/eloquent#observers
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Only model event methods allowed
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $method) {
            // Skip methods from parent classes
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            // Skip methods from traits
            if ($method->getFileName() !== $object->path) {
                continue;
            }

            if (in_array($method->getName(), $allowedMethods, true)) {
                continue;
            }

            $allowedList = 'creating, created, updating, updated, saving, saved, deleting, deleted, trashed, forceDeleting, forceDeleted, restoring, restored, replicating, retrieved';

            $message = <<<MSG
OBSERVER VIOLATION

RULE: Observers can only have model event methods.

REASON: Observer methods are automatically called by Laravel when model events
fire. Custom methods won't be called and indicate misplaced logic.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Location: {$object->path}:{$method->getStartLine()}

ALLOWED METHODS: {$allowedList}, __construct

FIX: Move custom logic to a Job, Action, or Listener
1. Dispatch a queued job from the observer
2. Fire an event and handle in a Listener
3. Keep observer focused on model events

REFERENCE: https://laravel.com/docs/eloquent#observers
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
                $message
            );
        }

        // Rule 3: No HTTP layer access (use statements)
        foreach ($forbiddenUses as $forbidden => $description) {
            if (str_contains($fileContent, "use {$forbidden}")) {
                $message = <<<MSG
OBSERVER VIOLATION

RULE: Observers cannot access the HTTP layer.

REASON: Observers may run in queue workers or artisan commands which have
no HTTP context. Request, Session, and Cookie data are not available.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}

FIX: Pass data through the model instead
1. Set model attributes before saving
2. Use model properties instead of request data

REFERENCE: https://laravel.com/docs/eloquent#observers
MSG;

                throw new ArchExpectationFailedException(
                    new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                    $message
                );
            }
        }

        // Rule 3: No HTTP layer access (helper functions)
        foreach ($forbiddenPatterns as $pattern => $description) {
            if (preg_match($pattern, $fileContent, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];
                $lineNumber = substr_count(substr($fileContent, 0, $position), "\n") + 1;

                $message = <<<MSG
OBSERVER VIOLATION

RULE: Observers cannot call HTTP helper functions.

REASON: Observers may run in queue workers or artisan commands which have
no HTTP context. The {$description} will not work as expected.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}:{$lineNumber}

FIX: Pass data through the model instead

REFERENCE: https://laravel.com/docs/eloquent#observers
MSG;

                throw new ArchExpectationFailedException(
                    new Violation($object->path, $lineNumber, $lineNumber),
                    $message
                );
            }
        }
    }

    expect(true)->toBeTrue();

    return $this;
});
