<?php

/**
 * Resource Expectation
 *
 * Enforces Laravel API Resource best practices:
 * - Must end with "Resource" or "Collection" suffix
 * - Must have toArray() method
 * - Only allowed public methods (toArray, with, additional, etc.)
 * - No database queries
 *
 * @see https://laravel.com/docs/eloquent-resources
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidResource', function () {
    $namespace = $this->value;

    // Allowed public methods in resources
    $allowedMethods = [
        '__construct',
        'toArray',
        'with',
        'additional',
        'jsonOptions',
        'withResponse',
        'resolve',
    ];

    // Database query patterns to detect
    $queryPatterns = [
        '/::find\s*\(/' => 'Model::find()',
        '/::findOrFail\s*\(/' => 'Model::findOrFail()',
        '/::where\s*\(/' => 'Model::where()',
        '/::all\s*\(/' => 'Model::all()',
        '/->get\s*\(\s*\)/' => '->get()',
        '/->first\s*\(\s*\)/' => '->first()',
        '/->count\s*\(\s*\)/' => '->count()',
        '/->pluck\s*\(/' => '->pluck()',
        '/->exists\s*\(\s*\)/' => '->exists()',
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

        // Rule 1: Must end with "Resource" or "Collection"
        if (! str_ends_with($shortClass, 'Resource') && ! str_ends_with($shortClass, 'Collection')) {
            $suggestedName = $shortClass.'Resource';

            $message = <<<MSG
RESOURCE VIOLATION

RULE: Resources must end with "Resource" or "Collection" suffix.

REASON: The Resource/Collection suffix makes transformation classes immediately
identifiable and follows Laravel's naming convention.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

EXAMPLES:
- UserData → UserResource
- UserTransformer → UserResource
- UserList → UserCollection

FIX: Rename to end with Resource or Collection
php artisan make:resource {$suggestedName}

REFERENCE: https://laravel.com/docs/eloquent-resources
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Must have toArray() method (defined in this class, not just inherited)
        $hasToArray = false;
        foreach ($reflection->getMethods() as $method) {
            if ($method->getName() === 'toArray' && $method->class === $reflection->getName()) {
                $hasToArray = true;
                break;
            }
        }

        if (! $hasToArray) {
            $message = <<<MSG
RESOURCE VIOLATION

RULE: Resources must have a toArray() method.

REASON: The toArray() method transforms the resource into an array for JSON
serialization. It's the core transformation method that must be implemented.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add toArray method
public function toArray(Request \$request): array
{
    return [
        'id' => \$this->id,
        'name' => \$this->name,
    ];
}

REFERENCE: https://laravel.com/docs/eloquent-resources#writing-resources
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 3: Only allowed public methods
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

            $allowedList = 'toArray, with, additional, jsonOptions, withResponse, __construct';

            $message = <<<MSG
RESOURCE VIOLATION

RULE: Resources can only have transformation methods.

REASON: Resources are for transformation only. Business logic, formatting,
and calculations belong in models, actions, or services.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Location: {$object->path}:{$method->getStartLine()}

ALLOWED METHODS: {$allowedList}

FIX: Move logic to model or action class
1. Use model accessors for formatting
2. Eager load calculated values in controller
3. Keep resources focused on transformation

REFERENCE: https://laravel.com/docs/eloquent-resources
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
                $message
            );
        }

        // Rule 4: No database queries
        foreach ($queryPatterns as $pattern => $description) {
            if (preg_match($pattern, $fileContent, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];
                $lineNumber = substr_count(substr($fileContent, 0, $position), "\n") + 1;

                $message = <<<MSG
RESOURCE VIOLATION

RULE: Resources cannot make database queries.

REASON: Resources transform data, they don't fetch it. Database queries in
resources cause N+1 problems and slow performance. Eager load relationships
in controllers and use whenLoaded() in resources.

VIOLATION:
- Class: {$shortClass}
- Pattern: {$description}
- Location: {$object->path}:{$lineNumber}

FIX: Eager load in controller, use whenLoaded() in resource
// Controller:
\$users = User::with('posts')->get();

// Resource:
'posts' => PostResource::collection(\$this->whenLoaded('posts')),

REFERENCE: https://laravel.com/docs/eloquent-resources#conditional-relationships
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
