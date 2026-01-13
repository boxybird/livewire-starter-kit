<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendExampleCommand extends Command
{
    protected $signature = 'example:send {--queue : Queue the operation}';

    protected $description = 'Example command demonstrating best practices';

    public function handle(): int
    {
        $this->info('Example command executed successfully.');

        return self::SUCCESS;
    }
}
