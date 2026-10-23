<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessingOutcomeEnum: string
{
    case Pending = 'pending';

    case Applied = 'applied';

    case Stale = 'stale';

    case Ignored = 'ignored';
}
