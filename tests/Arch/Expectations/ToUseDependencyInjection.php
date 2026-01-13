<?php

/**
 * Dependency Injection Expectation
 *
 * Enforces that Livewire components use method injection instead of
 * service location patterns like app() or resolve().
 *
 * @see https://livewire.laravel.com/docs/actions#dependency-injection
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toUseDependencyInjection', function () {
    $namespace = $this->value;

    // Forbidden service location patterns
    $forbiddenPatterns = [
        '/\bapp\s*\(/' => 'app() helper',
        '/\bresolve\s*\(/' => 'resolve() helper',
        '/\bapp\s*\(\s*\)\s*->\s*make\s*\(/' => 'app()->make()',
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

        // Only check classes that extend Livewire\Component
        if (! $reflection->isSubclassOf('Livewire\\Component')) {
            continue;
        }

        $shortClass = class_basename($reflection->getName());
        $fileContent = file_get_contents($object->path);

        // Check for forbidden patterns (service location)
        foreach ($forbiddenPatterns as $pattern => $description) {
            if (preg_match($pattern, $fileContent, $matches, PREG_OFFSET_CAPTURE)) {
                // Find line number
                $position = $matches[0][1];
                $lineNumber = substr_count(substr($fileContent, 0, $position), "\n") + 1;

                $message = <<<'MSG'
LIVEWIRE DEPENDENCY INJECTION VIOLATION

RULE: Livewire components must use method injection, not service location.

REASON: Service location (app(), resolve()) hides dependencies, makes testing harder,
and violates Laravel's dependency injection principles. Method injection makes
dependencies explicit, enables mocking, and follows Laravel conventions.

VIOLATION:
- Class: %s
- Forbidden: %s
- Location: %s:%d

FIX: Use method injection in your Livewire methods.

BEFORE (wrong):
public function viewPost(string $slug): void
{
    $blogService = app(BlogService::class);
    $this->selectedPost = $blogService->getPost($slug);
}

AFTER (correct):
public function viewPost(BlogService $blogService, string $slug): void
{
    $this->selectedPost = $blogService->getPost($slug);
}

NOTE: Livewire automatically resolves type-hinted dependencies from the container.
This works in mount(), render(), and all action methods.

CONTAINER BINDING: If the service has constructor dependencies that cannot be
auto-resolved (interfaces, primitives, or complex configuration), bind it in
AppServiceProvider:

public function register(): void
{
    $this->app->bind(BlogService::class, function ($app) {
        return new BlogService(
            $app->make(SomeInterface::class),
            config('blog.setting')
        );
    });
}

REFERENCE: https://livewire.laravel.com/docs/actions#dependency-injection
MSG;

                $formattedMessage = sprintf(
                    $message,
                    $shortClass,
                    $description,
                    $object->path,
                    $lineNumber
                );

                throw new ArchExpectationFailedException(
                    new Violation($object->path, $lineNumber, $lineNumber),
                    $formattedMessage
                );
            }
        }
    }

    expect(true)->toBeTrue();

    return $this;
});
