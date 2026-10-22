<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('notifications:dispatch-stuck')->everyMinute()->withoutOverlapping();
