<?php

/**
 * No Volt Expectation
 *
 * Enforces that Volt single-file components are not used.
 * This project uses class-based Livewire components only.
 *
 * @see https://livewire.laravel.com/docs/components
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toNotUseVolt', function () {
    $namespace = $this->value;

    // Volt-related imports to detect
    $voltPatterns = [
        'Livewire\\Volt\\Component' => 'Volt Component',
        'Livewire\\Volt\\' => 'Volt namespace',
        'use function Livewire\\Volt\\' => 'Volt functions',
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

        // Check for any Volt usage
        foreach ($voltPatterns as $pattern => $description) {
            if (str_contains($fileContent, $pattern)) {
                $message = <<<MSG
VOLT VIOLATION

RULE: This project uses class-based Livewire components only. Volt is not allowed.

REASON: Class-based components provide better architectural enforcement,
IDE support, static analysis, and consistency. Volt's single-file approach
makes it harder to validate component structure.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}

FIX: Convert to a class-based Livewire component
1. Create: app/Livewire/YourComponent.php extending Livewire\Component
2. Create: resources/views/livewire/your-component.blade.php for the template
3. Move PHP logic to the class, keep only Blade in the view

EXAMPLE:
// app/Livewire/Counter.php
use Livewire\Component;

class Counter extends Component
{
    public int \$count = 0;

    public function increment(): void
    {
        \$this->count++;
    }

    public function render()
    {
        return view('livewire.counter');
    }
}

REFERENCE: https://livewire.laravel.com/docs/components
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
