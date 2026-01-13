<?php

namespace App\Listeners;

use App\Events\ExampleCreated;
use Illuminate\Support\Facades\Log;

class LogExampleCreated
{
    public function handle(ExampleCreated $event): void
    {
        Log::info('Example created', $event->data);
    }
}
