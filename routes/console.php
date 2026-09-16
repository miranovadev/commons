<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('commons:participants-wake-due')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('commons:potential-refresh')->everyMinute()->onOneServer()->withoutOverlapping();
