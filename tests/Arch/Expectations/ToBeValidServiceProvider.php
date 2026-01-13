<?php

/**
 * Service Provider Expectation
 *
 * Enforces Laravel Service Provider best practices:
 * - Must end with "ServiceProvider" suffix
 * - Only allowed public methods (register, boot, provides, etc.)
 * - Deferred providers must have provides() method
 * - No HTTP layer dependencies
 *
 * @see https://laravel.com/docs/providers
 */

use Illuminate\Contracts\Support\DeferrableProvider;
use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidServiceProvider', function () {
    $namespace = $this->value;

    // Allowed public methods in service providers
    $allowedMethods = [
        '__construct',
        'register',
        'boot',
        'provides',
        'when',
        'isDeferred',
        // Helper methods from ServiceProvider base class
        'mergeConfigFrom',
        'loadRoutesFrom',
        'loadViewsFrom',
        'loadViewComponentsAs',
        'loadTranslationsFrom',
        'loadJsonTranslationsFrom',
        'loadMigrationsFrom',
        'publishes',
        'commands',
        'callAfterResolving',
        'booting',
        'booted',
        'packagePath',
        'defaultPolicies',
    ];

    // Forbidden use statements (HTTP layer)
    $forbiddenUses = [
        'Illuminate\\Http\\Request' => 'HTTP Request',
        'Illuminate\\Support\\Facades\\Request' => 'Request facade',
    ];

    // Forbidden patterns in code
    $forbiddenPatterns = [
        '/\\brequest\\s*\\(/' => 'request() helper',
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

        // Skip FortifyServiceProvider - it uses Request in rate limiter closures
        // which is valid since closures execute at request time, not bootstrap
        if ($reflection->getName() === 'App\\Providers\\FortifyServiceProvider') {
            continue;
        }

        $shortClass = class_basename($reflection->getName());
        $fileContent = file_get_contents($object->path);

        // Rule 1: Must end with "ServiceProvider"
        if (! str_ends_with($shortClass, 'ServiceProvider')) {
            $suggestedName = $shortClass.'ServiceProvider';

            $message = <<<MSG
SERVICE PROVIDER VIOLATION

RULE: Service providers must end with "ServiceProvider" suffix.

REASON: The ServiceProvider suffix makes provider classes immediately identifiable
and follows Laravel's naming convention for auto-discovery.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

EXAMPLES:
- PaymentProvider → PaymentServiceProvider
- FeatureFlagService → FeatureFlagServiceProvider
- AuthSetup → AuthServiceProvider

FIX: Rename to end with ServiceProvider
php artisan make:provider {$suggestedName}

REFERENCE: https://laravel.com/docs/providers
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Only allowed public methods
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

            $allowedList = 'register, boot, provides, __construct, and Laravel helper methods';

            $message = <<<MSG
SERVICE PROVIDER VIOLATION

RULE: Service providers can only have registration and boot methods.

REASON: Service providers should only register and configure services.
Business logic belongs in Actions, Services, or other classes. Having custom
public methods indicates business logic has leaked into the provider.

VIOLATION:
- Method: {$shortClass}::{$method->getName()}()
- Location: {$object->path}:{$method->getStartLine()}

ALLOWED METHODS: {$allowedList}

FIX: Move business logic to a dedicated Service or Action class
1. Create a Service class for the business logic
2. Register the Service in the provider's register() method
3. Keep the provider focused on registration

REFERENCE: https://laravel.com/docs/providers
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $method->getStartLine(), $method->getEndLine()),
                $message
            );
        }

        // Rule 3: Deferred provider must have provides() method
        if ($reflection->implementsInterface(DeferrableProvider::class)) {
            // Check if provides() is defined in this class (not just inherited)
            $hasProvidesMethod = false;
            foreach ($reflection->getMethods() as $method) {
                if ($method->getName() === 'provides' && $method->class === $reflection->getName()) {
                    $hasProvidesMethod = true;
                    break;
                }
            }

            if (! $hasProvidesMethod) {
                $message = <<<MSG
SERVICE PROVIDER VIOLATION

RULE: Deferred providers must implement the provides() method.

REASON: When a provider implements DeferrableProvider, Laravel needs to know
which services it provides so it can load the provider on-demand. Without
provides(), the deferred loading optimization won't work correctly.

VIOLATION:
- Class: {$shortClass} implements DeferrableProvider
- Missing: provides() method
- Location: {$object->path}

FIX: Add provides() method returning the services this provider registers
public function provides(): array
{
    return [
        'my-service',
        MyService::class,
    ];
}

REFERENCE: https://laravel.com/docs/providers#deferred-providers
MSG;

                throw new ArchExpectationFailedException(
                    new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                    $message
                );
            }
        }

        // Rule 4: No HTTP layer access (use statements)
        foreach ($forbiddenUses as $forbidden => $description) {
            if (str_contains($fileContent, "use {$forbidden}")) {
                $message = <<<MSG
SERVICE PROVIDER VIOLATION

RULE: Service providers cannot access the HTTP layer.

REASON: Service providers run during application bootstrap, before the HTTP
request is available. Accessing Request data in a provider will fail or
return unexpected results. Pass request data through service methods instead.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}

FIX: Remove HTTP dependencies from provider
1. Inject dependencies into Service classes instead
2. Access request data in Controllers or Middleware
3. Pass request data as method parameters

REFERENCE: https://laravel.com/docs/providers
MSG;

                throw new ArchExpectationFailedException(
                    new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                    $message
                );
            }
        }

        // Rule 4: No HTTP layer access (helper functions)
        foreach ($forbiddenPatterns as $pattern => $description) {
            if (preg_match($pattern, $fileContent, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];
                $lineNumber = substr_count(substr($fileContent, 0, $position), "\n") + 1;

                $message = <<<MSG
SERVICE PROVIDER VIOLATION

RULE: Service providers cannot call HTTP helper functions.

REASON: Service providers run during application bootstrap, before the HTTP
request is available. The {$description} will not work as expected during
provider registration or boot.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}:{$lineNumber}

FIX: Remove HTTP helpers from provider and pass data through services

REFERENCE: https://laravel.com/docs/providers
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
