<?php

/**
 * Policy Expectation
 *
 * Enforces Laravel Policy best practices:
 * - Must end with "Policy" suffix
 * - Only authorization methods allowed (viewAny, view, create, update, delete, restore, forceDelete, before)
 *
 * @see https://laravel.com/docs/authorization#creating-policies
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidPolicy', function () {
    $namespace = $this->value;

    // Allowed methods in policies
    $allowedMethods = [
        '__construct',
        'before',
        'viewAny',
        'view',
        'create',
        'update',
        'delete',
        'restore',
        'forceDelete',
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

        // Rule 1: Must end with "Policy"
        if (! str_ends_with($shortClass, 'Policy')) {
            $suggestedName = $shortClass.'Policy';

            $message = <<<MSG
POLICY VIOLATION

RULE: Policies must end with "Policy" suffix.

REASON: Laravel auto-discovers policies by convention. The Policy suffix
makes authorization classes immediately identifiable and enables automatic
model-to-policy resolution.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

EXAMPLES:
- UserAuthorization → UserPolicy
- PostPermissions → PostPolicy
- OrderAccess → OrderPolicy

FIX: Rename to end with Policy
php artisan make:policy {$suggestedName}

REFERENCE: https://laravel.com/docs/authorization#creating-policies
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Only authorization methods allowed
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

            $allowedList = implode(', ', $allowedMethods);

            $message = <<<MSG
POLICY VIOLATION

RULE: Policies can only have authorization methods.

REASON: Policies are for authorization only. Business logic belongs in
Actions, Services, or other classes. Standard methods are: viewAny, view,
create, update, delete, restore, forceDelete, before.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Location: {$object->path}:{$method->getStartLine()}

ALLOWED METHODS: {$allowedList}

FIX: Move business logic to an Action or Service class
1. Create an Action class for the business logic
2. Keep policies focused on yes/no authorization decisions

REFERENCE: https://laravel.com/docs/authorization#writing-policies
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
