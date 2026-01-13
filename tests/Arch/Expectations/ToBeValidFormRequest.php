<?php

/**
 * Form Request Expectation
 *
 * Enforces Form Request best practices:
 * - Naming: Store{Resource}Request or Update{Resource}Request
 * - Required: authorize() and rules() methods
 * - Allowed: authorize, rules, messages, attributes, after, __construct
 * - No helper methods or business logic
 *
 * @see https://laravel.com/docs/validation#form-request-validation
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidFormRequest', function () {
    $namespace = $this->value;
    $allowedMethods = ['__construct', 'authorize', 'rules', 'messages', 'attributes', 'after'];

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

        // Rule 1: Naming convention - must start with Store or Update
        if (! preg_match('/^(Store|Update).+Request$/', $shortClass)) {
            $message = <<<MSG
FORM REQUEST VIOLATION

RULE: Form Requests must be named Store{Resource}Request or Update{Resource}Request.

REASON: Consistent naming makes it clear what action the request validates.
Store = creating new resources, Update = modifying existing resources.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Rename to follow convention
- For create operations: Store{Resource}Request
- For update operations: Update{Resource}Request

REFERENCE: https://laravel.com/docs/validation#form-request-validation
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Must have authorize() method
        if (! $reflection->hasMethod('authorize')) {
            $message = <<<MSG
FORM REQUEST VIOLATION

RULE: Form Requests must have an authorize() method.

REASON: The authorize() method controls access to this action. Even if you
always return true, the explicit method makes authorization visible and auditable.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add authorize method
public function authorize(): bool
{
    return true; // Or add authorization logic
}

REFERENCE: https://laravel.com/docs/validation#authorizing-form-requests
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 3: Must have rules() method
        if (! $reflection->hasMethod('rules')) {
            $message = <<<MSG
FORM REQUEST VIOLATION

RULE: Form Requests must have a rules() method.

REASON: The rules() method defines validation rules for the request.
Without it, the Form Request provides no validation.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add rules method
/**
 * @return array<string, mixed>
 */
public function rules(): array
{
    return [
        'field' => ['required', 'string'],
    ];
}

REFERENCE: https://laravel.com/docs/validation#form-request-validation
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 4: Check for unauthorized public methods
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $method) {
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            if (in_array($method->getName(), $allowedMethods, true)) {
                continue;
            }

            $message = <<<MSG
FORM REQUEST VIOLATION

RULE: Form Requests can only have these methods: authorize, rules, messages, attributes, after.

REASON: Form Requests should only handle validation. Business logic like data
transformation or sanitization belongs in Action classes.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Location: {$object->path}:{$method->getStartLine()}

FIX: Move logic to an Action class
1. Put the logic in your Action's handle() method
2. Remove the method from the Form Request
3. Access validated data via \$request->validated()

REFERENCE: https://laravel.com/docs/validation#form-request-validation
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
                $message
            );
        }

        // Rule 5: No protected/private methods
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
FORM REQUEST VIOLATION

RULE: Form Requests should not have {$visibility} helper methods.

REASON: Helper methods in Form Requests indicate logic that should be extracted.
Validation rules should be declarative, not procedural.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Visibility: {$visibility}
- Location: {$object->path}:{$method->getStartLine()}

FIX: Remove helper method
- If it's validation logic: use Laravel's validation rules instead
- If it's business logic: move to an Action class
- If it's authorization logic: use the authorize() method with Gates/Policies

REFERENCE: https://laravel.com/docs/validation#available-validation-rules
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
