<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessingOutcomeEnum: string
{
    case Applied = 'applied';

    case Stale = 'stale';

    case Ignored = 'ignored';

    case Duplicate = 'duplicate';
}
