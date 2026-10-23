<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('webhooks:dispatch-stuck')->everyMinute()->withoutOverlapping();
Schedule::command('notifications:dispatch-stuck')->everyMinute()->withoutOverlapping();
