<?php

namespace App\Listeners;

use App\Actions\Services\CreateDefaultServicesForUserAction;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class SetupVerifiedUser
{
    public function __construct(
        private CreateDefaultServicesForUserAction $createDefaultServices,
    ) {}

    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->createDefaultServices->handle($event->user);
        }
    }
}
