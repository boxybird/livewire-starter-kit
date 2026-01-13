<?php

/**
 * Listener Expectation
 *
 * Enforces Laravel Listener best practices:
 * - Must have handle() method
 * - Verb-noun naming (SendWelcomeEmail, NotifyAdmin)
 * - No HTTP layer access (listeners can run in queue workers)
 *
 * @see https://laravel.com/docs/events#defining-listeners
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidListener', function () {
    $namespace = $this->value;

    // Verb prefixes for listener naming (similar to Jobs)
    $validPrefixes = [
        'Send', 'Notify', 'Update', 'Create', 'Delete', 'Process',
        'Generate', 'Sync', 'Log', 'Record', 'Track', 'Publish',
        'Archive', 'Cleanup', 'Verify', 'Validate', 'Calculate',
        'Build', 'Transform', 'Handle', 'Dispatch', 'Queue',
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
        '/\brequest\s*\(/' => 'request() helper',
        '/\bsession\s*\(/' => 'session() helper',
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

        // Rule 1: Must have handle() method
        if (! $reflection->hasMethod('handle')) {
            $message = <<<MSG
LISTENER VIOLATION

RULE: Listeners must have a handle() method.

REASON: The handle() method receives the event and performs the listener's action.
It's the entry point that Laravel calls when the event is dispatched.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add handle method
public function handle(SomeEvent \$event): void
{
    // React to the event
}

REFERENCE: https://laravel.com/docs/events#defining-listeners
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Verb-noun naming
        $startsWithVerb = false;
        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($shortClass, $prefix)) {
                $startsWithVerb = true;
                break;
            }
        }

        if (! $startsWithVerb) {
            $prefixList = implode(', ', array_slice($validPrefixes, 0, 10)).', ...';

            $message = <<<MSG
LISTENER VIOLATION

RULE: Listeners must be named with verb-noun pattern.

REASON: Listener names should describe the action performed in response to an event.
This makes the codebase more readable and the listener's purpose clear.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

VALID PREFIXES: {$prefixList}

EXAMPLES:
- WelcomeEmailSender → SendWelcomeEmail
- AdminNotifier → NotifyAdmin
- InventoryUpdater → UpdateInventory

FIX: Rename to start with a verb describing the action
php artisan make:listener Send{$shortClass}

REFERENCE: https://laravel.com/docs/events#defining-listeners
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 3: No HTTP layer access (use statements)
        foreach ($forbiddenUses as $forbidden => $description) {
            if (str_contains($fileContent, "use {$forbidden}")) {
                $message = <<<MSG
LISTENER VIOLATION

RULE: Listeners cannot access the HTTP layer.

REASON: Listeners may run in queue workers which have no HTTP context.
Request, Session, and Cookie data are not available when listeners are queued.
Pass any needed data through the event instead.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}

FIX: Pass data through the event
1. Add needed data to your Event class
2. Access it via \$event->propertyName in the listener

REFERENCE: https://laravel.com/docs/events#defining-listeners
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
LISTENER VIOLATION

RULE: Listeners cannot call HTTP helper functions.

REASON: Listeners may run in queue workers which have no HTTP context.
The {$description} will not work when the listener is queued.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}:{$lineNumber}

FIX: Pass data through the event instead

REFERENCE: https://laravel.com/docs/events#defining-listeners
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
