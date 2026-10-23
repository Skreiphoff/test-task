<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessWebhookEvent;
use App\Services\Marketplace\WebhookEventReceiver;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'webhooks:dispatch-stuck', description: 'Re-dispatch stored webhook events whose queue job was lost')]
class DispatchStuckWebhookEvents extends Command
{
    private const GRACE_SECONDS = 60;

    public function handle(WebhookEventReceiver $receiver): int
    {
        $count = 0;

        foreach ($receiver->stuck(self::GRACE_SECONDS) as $event) {
            ProcessWebhookEvent::dispatch($event->id);
            $count++;
        }

        $this->info("Dispatched {$count} pending event(s).");

        return self::SUCCESS;
    }
}
