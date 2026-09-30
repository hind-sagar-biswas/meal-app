<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('meal:log')->dailyAt('19:00');
Schedule::command('meal:log')->dailyAt('19:30');
Schedule::command('meal:log')->dailyAt('20:00');
