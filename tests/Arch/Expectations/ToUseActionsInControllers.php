<?php

/**
 * Controller Action Pattern Expectation
 *
 * Enforces that controller mutation methods (store, update, destroy) use the Action pattern:
 * - store(): FormRequest + Action
 * - update(): Model + FormRequest + Action
 * - destroy(): Model + Action
 *
 * This keeps controllers thin by delegating business logic to Action classes.
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toUseActionsInMutationMethods', function () {
    $namespace = $this->value;
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

        // Check store method: FormRequest + Action
        if ($reflection->hasMethod('store')) {
            $method = $reflection->getMethod('store');
            if ($method->class === $reflection->getName()) {
                validateStoreMethod($method, $object, $shortClass);
            }
        }

        // Check update method: Model + FormRequest + Action
        if ($reflection->hasMethod('update')) {
            $method = $reflection->getMethod('update');
            if ($method->class === $reflection->getName()) {
                validateUpdateMethod($method, $object, $shortClass);
            }
        }

        // Check destroy method: Model + Action
        if ($reflection->hasMethod('destroy')) {
            $method = $reflection->getMethod('destroy');
            if ($method->class === $reflection->getName()) {
                validateDestroyMethod($method, $object, $shortClass);
            }
        }
    }

    expect(true)->toBeTrue();

    return $this;
});

/**
 * Validate store() method signature: FormRequest + Action
 */
function validateStoreMethod(ReflectionMethod $method, object $object, string $shortClass): void
{
    $params = $method->getParameters();
    $resourceName = str_replace('Controller', '', $shortClass);

    // Check parameter count
    if (count($params) < 2) {
        throwStoreViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'store() must have at least 2 parameters: FormRequest and Action'
        );
    }

    // Check first parameter is FormRequest
    $firstParam = $params[0];
    if (! isFormRequest($firstParam)) {
        throwStoreViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'First parameter must be a FormRequest'
        );
    }

    // Check second parameter is Action
    $secondParam = $params[1];
    if (! isActionClass($secondParam)) {
        throwStoreViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'Second parameter must be an Action class'
        );
    }
}

/**
 * Validate update() method signature: Model + FormRequest + Action
 */
function validateUpdateMethod(ReflectionMethod $method, object $object, string $shortClass): void
{
    $params = $method->getParameters();
    $resourceName = str_replace('Controller', '', $shortClass);

    // Check parameter count
    if (count($params) < 3) {
        throwUpdateViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'update() must have at least 3 parameters: Model, FormRequest, and Action'
        );
    }

    // Check first parameter is Model
    $firstParam = $params[0];
    if (! isEloquentModel($firstParam)) {
        throwUpdateViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'First parameter must be an Eloquent Model (route model binding)'
        );
    }

    // Check second parameter is FormRequest
    $secondParam = $params[1];
    if (! isFormRequest($secondParam)) {
        throwUpdateViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'Second parameter must be a FormRequest'
        );
    }

    // Check third parameter is Action
    $thirdParam = $params[2];
    if (! isActionClass($thirdParam)) {
        throwUpdateViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'Third parameter must be an Action class'
        );
    }
}

/**
 * Validate destroy() method signature: Model + Action
 */
function validateDestroyMethod(ReflectionMethod $method, object $object, string $shortClass): void
{
    $params = $method->getParameters();
    $resourceName = str_replace('Controller', '', $shortClass);

    // Check parameter count
    if (count($params) < 2) {
        throwDestroyViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'destroy() must have at least 2 parameters: Model and Action'
        );
    }

    // Check first parameter is Model
    $firstParam = $params[0];
    if (! isEloquentModel($firstParam)) {
        throwDestroyViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'First parameter must be an Eloquent Model (route model binding)'
        );
    }

    // Check second parameter is Action
    $secondParam = $params[1];
    if (! isActionClass($secondParam)) {
        throwDestroyViolation(
            $method,
            $object,
            $shortClass,
            $resourceName,
            'Second parameter must be an Action class'
        );
    }
}

/**
 * Check if parameter extends FormRequest
 */
function isFormRequest(ReflectionParameter $param): bool
{
    if (! $param->hasType()) {
        return false;
    }

    $type = $param->getType();
    if (! $type instanceof ReflectionNamedType) {
        return false;
    }

    $typeName = $type->getName();

    if (! class_exists($typeName)) {
        return false;
    }

    return is_subclass_of($typeName, FormRequest::class);
}

/**
 * Check if parameter is an Eloquent Model
 */
function isEloquentModel(ReflectionParameter $param): bool
{
    if (! $param->hasType()) {
        return false;
    }

    $type = $param->getType();
    if (! $type instanceof ReflectionNamedType) {
        return false;
    }

    $typeName = $type->getName();

    if (! class_exists($typeName)) {
        return false;
    }

    return is_subclass_of($typeName, Model::class);
}

/**
 * Check if parameter is an Action class (in App\Actions namespace or ends with Action)
 */
function isActionClass(ReflectionParameter $param): bool
{
    if (! $param->hasType()) {
        return false;
    }

    $type = $param->getType();
    if (! $type instanceof ReflectionNamedType) {
        return false;
    }

    $typeName = $type->getName();

    // Check if in App\Actions namespace
    if (str_starts_with($typeName, 'App\\Actions\\')) {
        return true;
    }

    // Check if class name ends with Action
    return str_ends_with($typeName, 'Action');
}

/**
 * Throw violation for store() method
 */
function throwStoreViolation(
    ReflectionMethod $method,
    object $object,
    string $shortClass,
    string $resourceName,
    string $rule
): never {
    $message = <<<MSG
CONTROLLER ACTION PATTERN VIOLATION

RULE: {$rule}

REASON: The store() method should accept a FormRequest for validation and an
Action class for business logic. This keeps controllers thin and focused on
HTTP concerns while delegating business logic to reusable Action classes.

VIOLATION:
- Method: {$shortClass}::store()
- Location: {$object->path}:{$method->getStartLine()}

EXPECTED SIGNATURE:
public function store(Store{$resourceName}Request \$request, Create{$resourceName}Action \$action)

FIX:
1. Create FormRequest: php artisan make:request Store{$resourceName}Request
2. Create Action: app/Actions/Create{$resourceName}Action.php
3. Update signature: public function store(Store{$resourceName}Request \$request, Create{$resourceName}Action \$action)
4. In method body: return \$action->handle(\$request->validated());

REFERENCE: https://laravelactions.com/
MSG;

    throw new ArchExpectationFailedException(
        new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
        $message
    );
}

/**
 * Throw violation for update() method
 */
function throwUpdateViolation(
    ReflectionMethod $method,
    object $object,
    string $shortClass,
    string $resourceName,
    string $rule
): never {
    $message = <<<MSG
CONTROLLER ACTION PATTERN VIOLATION

RULE: {$rule}

REASON: The update() method should accept a Model (route model binding), a
FormRequest for validation, and an Action class for business logic. This
pattern ensures proper resource resolution, validation, and business logic separation.

VIOLATION:
- Method: {$shortClass}::update()
- Location: {$object->path}:{$method->getStartLine()}

EXPECTED SIGNATURE:
public function update({$resourceName} \${$resourceName}, Update{$resourceName}Request \$request, Update{$resourceName}Action \$action)

FIX:
1. Ensure Model exists: app/Models/{$resourceName}.php
2. Create FormRequest: php artisan make:request Update{$resourceName}Request
3. Create Action: app/Actions/Update{$resourceName}Action.php
4. Update signature with all 3 parameters
5. In method body: return \$action->handle(\${$resourceName}, \$request->validated());

REFERENCE: https://laravelactions.com/
MSG;

    throw new ArchExpectationFailedException(
        new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
        $message
    );
}

/**
 * Throw violation for destroy() method
 */
function throwDestroyViolation(
    ReflectionMethod $method,
    object $object,
    string $shortClass,
    string $resourceName,
    string $rule
): never {
    $message = <<<MSG
CONTROLLER ACTION PATTERN VIOLATION

RULE: {$rule}

REASON: The destroy() method should accept a Model (route model binding) and
an Action class for deletion logic. Even simple deletes benefit from Action
classes for consistency, soft-delete handling, and related cleanup logic.

VIOLATION:
- Method: {$shortClass}::destroy()
- Location: {$object->path}:{$method->getStartLine()}

EXPECTED SIGNATURE:
public function destroy({$resourceName} \${$resourceName}, Delete{$resourceName}Action \$action)

FIX:
1. Ensure Model exists: app/Models/{$resourceName}.php
2. Create Action: app/Actions/Delete{$resourceName}Action.php
3. Update signature with both parameters
4. In method body: return \$action->handle(\${$resourceName});

REFERENCE: https://laravelactions.com/
MSG;

    throw new ArchExpectationFailedException(
        new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
        $message
    );
}
