<?php

/**
 * Middleware Expectation
 *
 * Enforces Laravel Middleware best practices:
 * - Must have handle() method
 * - Verb-based naming (EnsureUserIsAdmin, ValidateApiToken)
 *
 * @see https://laravel.com/docs/middleware
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidMiddleware', function () {
    $namespace = $this->value;

    // Verb prefixes for middleware naming
    $validPrefixes = [
        'Authenticate', 'Authorize', 'Validate', 'Verify', 'Check', 'Ensure',
        'Trim', 'Convert', 'Handle', 'Encrypt', 'Decrypt', 'Throttle', 'Start',
        'Share', 'Add', 'Redirect', 'Prevent', 'Trust', 'Set', 'Log', 'Track',
        'Record', 'Invoke', 'Substitute', 'Transform', 'Require',
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

        // Rule 1: Must have handle() method
        if (! $reflection->hasMethod('handle')) {
            $message = <<<MSG
MIDDLEWARE VIOLATION

RULE: Middleware must have a handle() method.

REASON: The handle() method is the entry point that Laravel calls for every request.
It receives the request, passes it to the next middleware, and returns the response.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add handle method
public function handle(Request \$request, Closure \$next): Response
{
    // Middleware logic here
    return \$next(\$request);
}

REFERENCE: https://laravel.com/docs/middleware
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Verb-based naming
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
MIDDLEWARE VIOLATION

RULE: Middleware must be named with verb prefix.

REASON: Middleware names should describe what action they perform.
This makes the middleware's purpose immediately clear.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

VALID PREFIXES: {$prefixList}

EXAMPLES:
- AdminMiddleware → EnsureUserIsAdmin
- ApiMiddleware → ValidateApiToken
- AuthMiddleware → Authenticate

FIX: Rename with verb prefix
php artisan make:middleware Ensure{$shortClass}

REFERENCE: https://laravel.com/docs/middleware
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }
    }

    expect(true)->toBeTrue();

    return $this;
});
