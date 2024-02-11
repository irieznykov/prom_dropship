<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('price:update')
            ->everyTwoHours()
            ->sendOutputTo(storage_path('logs/schedule.log'));
        $schedule->command('price:update')
            ->everyTwoHours(1)
            ->sendOutputTo(storage_path('logs/schedule.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
