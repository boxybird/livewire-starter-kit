<?php

namespace App\Observers;

use App\Models\User;

class ExampleObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Handle the event
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Handle the event
    }
}
