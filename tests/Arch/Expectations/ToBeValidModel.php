<?php

/**
 * Eloquent Model Expectation
 *
 * Enforces Model best practices NOT covered by Larastan/Rector:
 * - No HTTP layer access (Request, Session, Cookie)
 * - Must use $fillable (not $guarded)
 * - No direct service/container calls (app(), resolve())
 *
 * @see https://laravel.com/docs/eloquent
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidModel', function () {
    $namespace = $this->value;

    // Forbidden use statements (HTTP layer)
    $forbiddenUses = [
        'Illuminate\\Http\\Request' => 'HTTP Request',
        'Illuminate\\Support\\Facades\\Session' => 'Session facade',
        'Illuminate\\Support\\Facades\\Cookie' => 'Cookie facade',
        'Illuminate\\Support\\Facades\\Request' => 'Request facade',
    ];

    // Forbidden patterns in code (service/container calls)
    $forbiddenPatterns = [
        '/\bapp\s*\(/' => 'app() helper',
        '/\bresolve\s*\(/' => 'resolve() helper',
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

        // Rule 1: Check for forbidden use statements (HTTP layer)
        foreach ($forbiddenUses as $forbidden => $description) {
            if (str_contains($fileContent, "use {$forbidden}")) {
                $message = <<<MSG
MODEL VIOLATION

RULE: Models cannot access the HTTP layer.

REASON: Models should be pure data objects focused on persistence and relationships.
HTTP concerns (Request, Session, Cookie) belong in Controllers or Middleware.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}

FIX: Remove HTTP dependency
- If you need request data: pass it as a parameter from the Controller
- If you need session data: pass it from Controller or use an Action class
- If you need auth user: use a scope that accepts User as parameter

REFERENCE: https://laravel.com/docs/eloquent
MSG;

                throw new ArchExpectationFailedException(
                    new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                    $message
                );
            }
        }

        // Rule 2: Check for forbidden patterns (service/container calls)
        foreach ($forbiddenPatterns as $pattern => $description) {
            if (preg_match($pattern, $fileContent, $matches, PREG_OFFSET_CAPTURE)) {
                // Find line number
                $position = $matches[0][1];
                $lineNumber = substr_count(substr($fileContent, 0, $position), "\n") + 1;

                $message = <<<MSG
MODEL VIOLATION

RULE: Models cannot call service container or HTTP helpers.

REASON: Models should be pure data objects. Business logic and service calls
belong in Action classes. This keeps models focused on data persistence.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}:{$lineNumber}

FIX: Move logic to an Action class
1. Create an Action: app/Actions/{$shortClass}Action.php
2. Inject the service in the Action's constructor
3. Call the Action from your Controller

REFERENCE: https://laravel.com/docs/eloquent
MSG;

                throw new ArchExpectationFailedException(
                    new Violation($object->path, $lineNumber, $lineNumber),
                    $message
                );
            }
        }

        // Rule 3: Must have $fillable property
        if (! $reflection->hasProperty('fillable')) {
            $message = <<<MSG
MODEL VIOLATION

RULE: Models must define the \$fillable property.

REASON: Explicit whitelisting (\$fillable) is safer than blacklisting (\$guarded).
It forces developers to consciously decide what fields are mass-assignable.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add \$fillable property
/**
 * @var list<string>
 */
protected \$fillable = [
    'field1',
    'field2',
];

REFERENCE: https://laravel.com/docs/eloquent#mass-assignment
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 4: Must NOT have $guarded property
        if ($reflection->hasProperty('guarded')) {
            $guardedProperty = $reflection->getProperty('guarded');
            // Only flag if it's declared in this class, not inherited
            if ($guardedProperty->class === $reflection->getName()) {
                $message = <<<MSG
MODEL VIOLATION

RULE: Models must use \$fillable, not \$guarded.

REASON: \$guarded uses a blacklist approach which can accidentally expose new fields
when they're added to the database. \$fillable requires explicit opt-in for each
mass-assignable field, which is safer.

VIOLATION:
- Class: {$shortClass}
- Property: \$guarded
- Location: {$object->path}

FIX: Replace \$guarded with \$fillable
1. Remove: protected \$guarded = [...];
2. Add: protected \$fillable = ['field1', 'field2', ...];
3. List only fields that should be mass-assignable

REFERENCE: https://laravel.com/docs/eloquent#mass-assignment
MSG;

                throw new ArchExpectationFailedException(
                    new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                    $message
                );
            }
        }
    }

    expect(true)->toBeTrue();

    return $this;
});
