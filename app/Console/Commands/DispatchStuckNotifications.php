<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendOrderNotification;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'notifications:dispatch-stuck', description: 'Re-dispatch pending notifications whose queue job was lost')]
class DispatchStuckNotifications extends Command
{
    private const GRACE_SECONDS = 60;

    public function handle(NotificationOutbox $outbox): int
    {
        $count = 0;

        foreach ($outbox->stuck(self::GRACE_SECONDS) as $notification) {
            SendOrderNotification::dispatch($notification->order_id, $notification->kind);
            $count++;
        }

        $this->info("Dispatched {$count} pending notification(s).");

        return self::SUCCESS;
    }
}
