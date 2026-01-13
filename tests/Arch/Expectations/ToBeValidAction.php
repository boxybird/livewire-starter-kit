<?php

/**
 * Action Class Expectation
 *
 * Enforces that action classes follow the single-responsibility pattern:
 * - Only one public method: handle()
 * - No protected/private methods (extract to services)
 *
 * @see https://laravelactions.com/
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidAction', function () {
    $namespace = $this->value;
    $allowedMethods = ['__construct', 'handle'];

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

        // Skip Fortify actions - they use Laravel's official conventions
        if (str_starts_with($reflection->getName(), 'App\\Actions\\Fortify\\')) {
            continue;
        }

        $shortClass = class_basename($reflection->getName());

        // Check for required handle() method
        if (! $reflection->hasMethod('handle')) {
            $message = <<<MSG
ACTION CLASS VIOLATION

RULE: Action classes must have a handle() method.

REASON: The handle() method is the single entry point for executing the action.
This keeps action classes focused on one specific operation.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add the handle() method
public function handle(/* params */): ReturnType
{
    // Action logic here
}

REFERENCE: https://laravelactions.com/
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Check for non-public methods (protected/private)
        $nonPublicMethods = array_merge(
            $reflection->getMethods(ReflectionMethod::IS_PROTECTED),
            $reflection->getMethods(ReflectionMethod::IS_PRIVATE)
        );

        foreach ($nonPublicMethods as $method) {
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            $visibility = $method->isProtected() ? 'protected' : 'private';

            $message = <<<MSG
ACTION CLASS VIOLATION

RULE: Action classes should not have {$visibility} methods.

REASON: Action classes should be simple, focused operations. Helper methods
indicate logic that should be extracted to a dedicated service class for
better testability and reuse.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Visibility: {$visibility}
- Location: {$object->path}:{$method->getStartLine()}

FIX: Extract to a service class
1. Create: app/Services/{$shortClass}Service.php (or domain-specific service)
2. Inject: __construct(private MyService \$service)
3. Call: \$this->service->{$method->getName()}()

REFERENCE: https://laravel.com/docs/container
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
                $message
            );
        }

        // Check for extra public methods beyond handle() and __construct
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $method) {
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            if (in_array($method->getName(), $allowedMethods, true)) {
                continue;
            }

            $message = <<<MSG
ACTION CLASS VIOLATION

RULE: Action classes must have exactly one public method: handle()
ALLOWED: __construct, handle

REASON: Each action class represents a single operation. Multiple public
methods indicate the action is doing too much and should be split into
separate action classes.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Location: {$object->path}:{$method->getStartLine()}

FIX: Extract to a separate action class
1. Create: app/Actions/{$method->getName()}Action.php (use descriptive verb-noun name)
2. Move the logic to the new action's handle() method
3. Call from original action if needed:
   app({$method->getName()}Action::class)->handle(...)
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
