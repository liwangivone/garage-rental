<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Status garasi mengikuti tanggal sewa: dijalankan setiap hari pukul 00:01 (WIB).
Schedule::command('garages:sync-status')->dailyAt('00:01');
