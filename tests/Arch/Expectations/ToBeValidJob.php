<?php

/**
 * Job Expectation
 *
 * Enforces Laravel Job best practices:
 * - Must implement ShouldQueue
 * - Must have handle() method
 * - Verb-noun naming (ProcessPayment, SendEmail)
 * - No HTTP layer access
 *
 * @see https://laravel.com/docs/queues
 */

use Illuminate\Contracts\Queue\ShouldQueue;
use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\ValueObjects\Violation;

expect()->extend('toBeValidJob', function () {
    $namespace = $this->value;

    // Verb prefixes for job naming
    $validPrefixes = [
        'Process', 'Send', 'Generate', 'Sync', 'Import', 'Export',
        'Notify', 'Update', 'Create', 'Delete', 'Dispatch', 'Handle',
        'Calculate', 'Publish', 'Archive', 'Cleanup', 'Verify', 'Validate',
        'Fetch', 'Build', 'Transform', 'Parse', 'Execute', 'Run',
    ];

    // Forbidden use statements (HTTP layer)
    $forbiddenUses = [
        'Illuminate\\Http\\Request' => 'HTTP Request',
        'Illuminate\\Support\\Facades\\Session' => 'Session facade',
        'Illuminate\\Support\\Facades\\Cookie' => 'Cookie facade',
        'Illuminate\\Support\\Facades\\Request' => 'Request facade',
    ];

    // Forbidden patterns in code
    $forbiddenPatterns = [
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

        // Rule 1: Must implement ShouldQueue
        if (! $reflection->implementsInterface(ShouldQueue::class)) {
            $message = <<<MSG
JOB VIOLATION

RULE: Jobs must implement ShouldQueue interface.

REASON: Jobs should be processed asynchronously by queue workers.
Without ShouldQueue, the job runs synchronously which defeats the purpose
of using a job class.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Implement ShouldQueue
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class {$shortClass} implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
}

REFERENCE: https://laravel.com/docs/queues#creating-jobs
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 2: Must have handle() method
        if (! $reflection->hasMethod('handle')) {
            $message = <<<MSG
JOB VIOLATION

RULE: Jobs must have a handle() method.

REASON: The handle() method is the entry point that executes when the job
is processed by the queue worker. Without it, the job cannot perform any work.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

FIX: Add handle method
public function handle(): void
{
    // Job logic here
}

REFERENCE: https://laravel.com/docs/queues#class-structure
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 3: Naming convention - must start with verb
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
JOB VIOLATION

RULE: Jobs must be named with verb-noun pattern.

REASON: Job names should describe the action being performed. This makes
the codebase more readable and the job's purpose immediately clear.

VIOLATION:
- Class: {$shortClass}
- Location: {$object->path}

VALID PREFIXES: {$prefixList}

EXAMPLES:
- ProcessPayment (not PaymentProcessor)
- SendWelcomeEmail (not WelcomeEmailSender)
- GenerateReport (not ReportGenerator)
- SyncInventory (not InventorySyncer)

FIX: Rename to start with a verb
php artisan make:job Process{$shortClass}

REFERENCE: https://laravel.com/docs/queues#creating-jobs
MSG;

            throw new ArchExpectationFailedException(
                new Violation($object->path, $reflection->getStartLine(), $reflection->getEndLine()),
                $message
            );
        }

        // Rule 4: No HTTP layer access (use statements)
        foreach ($forbiddenUses as $forbidden => $description) {
            if (str_contains($fileContent, "use {$forbidden}")) {
                $message = <<<MSG
JOB VIOLATION

RULE: Jobs cannot access the HTTP layer.

REASON: Jobs run in queue workers which have no HTTP context. Request,
Session, and Cookie data are not available when jobs are processed.
Pass any needed data through the job's constructor instead.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}

FIX: Pass data through constructor
class {$shortClass} implements ShouldQueue
{
    public function __construct(
        public readonly array \$data,  // Pass data explicitly
    ) {}

    public function handle(): void
    {
        // Use \$this->data instead of request()
    }
}

REFERENCE: https://laravel.com/docs/queues#class-structure
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
JOB VIOLATION

RULE: Jobs cannot call HTTP helper functions.

REASON: Jobs run in queue workers which have no HTTP context. The {$description}
will not work as expected when the job is processed asynchronously.

VIOLATION:
- Class: {$shortClass}
- Forbidden: {$description}
- Location: {$object->path}:{$lineNumber}

FIX: Pass data through constructor instead of using {$description}

REFERENCE: https://laravel.com/docs/queues#class-structure
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
