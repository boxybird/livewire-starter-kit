<?php

/**
 * Event Expectation
 *
 * Enforces Laravel Event best practices:
 * - Past-tense naming (UserCreated, OrderPlaced)
 * - No business logic (data containers only)
 * - Only allowed methods: __construct, broadcastOn, broadcastAs, broadcastWith
 *
 * @see https://laravel.com/docs/events
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidEvent', function () {
    $namespace = $this->value;

    // Past tense suffixes that indicate an event happened
    $pastTenseSuffixes = [
        'Created', 'Updated', 'Deleted', 'Removed', 'Added', 'Changed',
        'Completed', 'Failed', 'Started', 'Finished', 'Processed', 'Sent',
        'Received', 'Placed', 'Cancelled', 'Approved', 'Rejected', 'Verified',
        'Registered', 'Logged', 'Published', 'Archived', 'Restored', 'Synced',
        'Imported', 'Exported', 'Generated', 'Submitted', 'Expired', 'Renewed',
    ];

    // Allowed methods for events (data containers + broadcasting)
    $allowedMethods = [
        '__construct',
        'broadcastOn',
        'broadcastAs',
        'broadcastWith',
        'broadcastWhen',
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

        // Rule 1: Past-tense naming - must end with past tense suffix
        $hasPastTense = false;
        foreach ($pastTenseSuffixes as $suffix) {
            if (str_ends_with($shortClass, $suffix)) {
                $hasPastTense = true;
                break;
            }
        }

        if (! $hasPastTense) {
            $suffixList = implode(', ', array_slice($pastTenseSuffixes, 0, 10)).', ...';

            $message = <<<MSG
EVENT VIOLATION

RULE: Events must be named in past tense (something that happened).

REASON: Events represent facts that have occurred. Past tense naming
makes this clear and distinguishes events from commands/actions.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

VALID SUFFIXES: {$suffixList}

EXAMPLES:
- CreateUser → UserCreated
- PlaceOrder → OrderPlaced
- ProcessPayment → PaymentProcessed
- SendEmail → EmailSent

FIX: Rename to past tense describing what happened
php artisan make:event {$shortClass}ed

REFERENCE: https://laravel.com/docs/events
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: No business logic - only allowed methods
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $method) {
            // Skip methods from parent classes
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            // Skip methods from traits (like Dispatchable::dispatch)
            if ($method->getFileName() !== $object->path) {
                continue;
            }

            if (in_array($method->getName(), $allowedMethods, true)) {
                continue;
            }

            $message = <<<MSG
EVENT VIOLATION

RULE: Events can only have these methods: __construct, broadcastOn, broadcastAs, broadcastWith.

REASON: Events are data containers that represent something that happened.
They should not contain business logic - that belongs in listeners or actions.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Location: {$object->path}:{$method->getStartLine()}

FIX: Remove business logic from event
1. Events should only hold data about what happened
2. Move any logic to a Listener class
3. Use public readonly properties for event data

EXAMPLE:
class UserCreated
{
    public function __construct(
        public readonly User \$user,
    ) {}
}

REFERENCE: https://laravel.com/docs/events
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
                $message
            );
        }
    }

    expect(true)->toBeTrue();

    return $this;
});
