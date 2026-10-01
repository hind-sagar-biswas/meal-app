<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('month:ensure-current')->dailyAt('00:01');
Schedule::command('month:ensure-current')->dailyAt('00:10');

Schedule::command('meal:log')->dailyAt('19:00');
Schedule::command('meal:log')->dailyAt('19:30');
Schedule::command('meal:log')->dailyAt('20:00');

Schedule::command('messdb:backup')->dailyAt('03:00');
Schedule::command('messdb:cleanup')->dailyAt('03:30');
