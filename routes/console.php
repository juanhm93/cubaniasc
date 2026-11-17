<?php

use App\Models\StudentLoginCode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Housekeeping. Needs the cPanel cron job `php artisan schedule:run` every minute.
*/
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('model:prune', ['--model' => [StudentLoginCode::class]])->daily();
