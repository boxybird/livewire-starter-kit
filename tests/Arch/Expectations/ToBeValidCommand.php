<?php

/**
 * Command Expectation
 *
 * Enforces Laravel Console Command best practices:
 * - Must have $signature property
 * - Must have handle() or __invoke() method
 * - Verb-noun naming (SendEmails, ProcessData)
 *
 * @see https://laravel.com/docs/artisan
 */

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidCommand', function () {
    $namespace = $this->value;

    // Verb prefixes for command naming
    $validPrefixes = [
        'Send', 'Process', 'Generate', 'Sync', 'Import', 'Export', 'Prune',
        'Cleanup', 'Run', 'Execute', 'Build', 'Create', 'Delete', 'Update',
        'Notify', 'Dispatch', 'Publish', 'Fetch', 'Calculate', 'Validate',
        'Check', 'Refresh', 'Clear', 'Cache', 'Queue', 'Schedule', 'Migrate',
        'Seed', 'Install', 'Setup', 'Configure', 'Reset', 'Restore', 'Backup',
        'Make',
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

        // Rule 1: Must have $signature property
        if (! $reflection->hasProperty('signature')) {
            $message = <<<MSG
COMMAND VIOLATION

RULE: Commands must have a \$signature property.

REASON: The \$signature property defines the command name, arguments, and options.
Without it, Laravel cannot register or execute the command correctly.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add \$signature property
protected \$signature = 'command:name {argument} {--option}';

REFERENCE: https://laravel.com/docs/artisan#defining-input-expectations
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Must have handle() or __invoke() method
        $hasHandle = $reflection->hasMethod('handle');
        $hasInvoke = $reflection->hasMethod('__invoke');

        if (! $hasHandle && ! $hasInvoke) {
            $message = <<<MSG
COMMAND VIOLATION

RULE: Commands must have a handle() method.

REASON: The handle() method is the entry point that Laravel calls when the
command is executed. It's where your command logic should be placed.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add handle method
public function handle(): int
{
    \$this->info('Command executed successfully.');
    return self::SUCCESS;
}

REFERENCE: https://laravel.com/docs/artisan#command-structure
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 3: Verb-noun naming
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
COMMAND VIOLATION

RULE: Commands must be named with verb prefix.

REASON: Command names should describe the action being performed.
This makes the command's purpose immediately clear.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

VALID PREFIXES: {$prefixList}

EXAMPLES:
- EmailCommand → SendEmails
- ReportGenerator → GenerateReport
- DataProcessor → ProcessData
- StaleRecordsCleaner → PruneStaleRecords

FIX: Rename with verb prefix describing the action
php artisan make:command Send{$shortClass}

REFERENCE: https://laravel.com/docs/artisan
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
