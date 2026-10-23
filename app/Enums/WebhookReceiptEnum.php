<?php

declare(strict_types=1);

namespace App\Enums;

/** What the webhook endpoint answers: the event is only stored here, applying it is the worker's job. */
enum WebhookReceiptEnum: string
{
    case Accepted = 'accepted';

    case Duplicate = 'duplicate';

    case Ignored = 'ignored';
}
