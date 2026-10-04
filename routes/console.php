<?php

use App\Actions\SendPaymentReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cubania:send-payment-reminders {--limit=500 : Maximum students to remind in this run}', function (SendPaymentReminders $sendPaymentReminders) {
    $sent = $sendPaymentReminders->execute((int) $this->option('limit'));

    $this->info("Recordatorios de pago enviados: {$sent}.");
})->purpose('Send the monthly payment reminders due today (normally triggered by the first web visits of the day)');
