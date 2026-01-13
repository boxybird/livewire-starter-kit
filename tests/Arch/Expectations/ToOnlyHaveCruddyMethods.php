<?php

/**
 * Cruddy by Design Expectation
 *
 * Enforces that controllers only have the 7 RESTful actions (index, create,
 * store, show, edit, update, destroy) and no protected/private methods.
 *
 * Based on Adam Wathan's "Cruddy by Design" talk.
 *
 * @see https://www.youtube.com/watch?v=MF0jFKvS4SI
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toOnlyHaveCruddyMethods', function (array $besides = []) {
    $namespace = $this->value;

    // Standard CRUD methods allowed in controllers (Cruddy by Design)
    $crudMethods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
    $systemMethods = ['__construct', '__invoke', 'middleware'];
    $allowedMethods = array_merge($crudMethods, $systemMethods, $besides);

    // Use Pest's class discovery
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
CRUDDY BY DESIGN VIOLATION

RULE: Controllers must only have public methods.

REASON: Controller methods are HTTP entry points invoked by Laravel's router.
{$visibility} methods cannot be routed and indicate logic that should be
extracted to a service class for better testability and reuse.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Visibility: {$visibility}
- Location: {$object->path}:{$method->getStartLine()}

FIX: Extract to a service class
- Create: app/Services/{$shortClass}Service.php
- Inject: __construct(public {$shortClass}Service \$service)
- Call: \$this->service->{$method->getName()}()

REFERENCE: https://laravel.com/docs/controllers
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
                $message
            );
        }

        // Check for non-CRUD public methods
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $method) {
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            if (in_array($method->getName(), $allowedMethods, true)) {
                continue;
            }

            // Extract potential resource name from method (e.g., "uploadImage" -> "Image")
            $methodName = $method->getName();
            $potentialResource = ucfirst((string) preg_replace('/^(get|set|add|remove|update|delete|upload|download|create|store|show|edit|destroy)/', '', $methodName));
            if ($potentialResource === '' || $potentialResource === '0') {
                $potentialResource = ucfirst($methodName);
            }

            // Suggest nested controller name (e.g., PostController + Image -> PostImageController)
            $parentResource = str_replace('Controller', '', $shortClass);
            $nestedController = "{$parentResource}{$potentialResource}Controller";

            $message = <<<MSG
CRUDDY BY DESIGN VIOLATION

RULE: Controllers must only have the 7 RESTful actions.
ALLOWED: index, create, store, show, edit, update, destroy

REASON: Custom actions like '{$methodName}()' indicate a missing resource.
Instead of adding methods to existing controllers, create a new controller
for the nested or related resource. This keeps controllers focused and RESTful.

VIOLATION:
- Method: {$shortClass}::{$methodName}()
- Location: {$object->path}:{$method->getStartLine()}

FIX: Create a nested resource controller

Example for "{$methodName}" on {$shortClass}:

1. Create controller: php artisan make:controller {$nestedController}

2. Add nested route:
   Route::resource('{$parentResource}.{$potentialResource}', {$nestedController}::class);
   // Or for a single action:
   Route::post('/{$parentResource}/{{$parentResource}}/{$potentialResource}', [{$nestedController}::class, 'store']);

3. Move logic to the new controller's CRUD method (likely store/update/destroy)

PATTERN: POST /posts/{post}/images → PostImageController@store

REFERENCE: https://laravel.com/docs/controllers#resource-controllers
VIDEO: "Cruddy by Design" by Adam Wathan - https://www.youtube.com/watch?v=MF0jFKvS4SI
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
