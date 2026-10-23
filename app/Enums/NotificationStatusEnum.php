<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationStatusEnum: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Skipped = 'skipped';
}
