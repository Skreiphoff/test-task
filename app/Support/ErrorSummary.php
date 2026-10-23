<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * What is stored next to a failed record. The message is left out on purpose: database errors
 * carry the SQL with its bindings, i.e. customer data. The full exception stays in the application log.
 */
final class ErrorSummary
{
    public static function of(Throwable $error): string
    {
        return sprintf('%s (code %s)', $error::class, $error->getCode());
    }
}
