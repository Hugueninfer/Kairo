<?php

use App\Jobs\ExpireInvitations;
use App\Jobs\ExpireQuotes;
use App\Jobs\GenerateRecurringInvoices;
use App\Jobs\MarkOverdueInvoices;
use App\Jobs\SendInvoiceReminders;
use App\Jobs\StopForgottenTimers;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireInvitations)->dailyAt('03:00')->onOneServer();
Schedule::job(new StopForgottenTimers)->hourly()->onOneServer();
Schedule::job(new ExpireQuotes)->dailyAt('03:10')->onOneServer();
Schedule::job(new MarkOverdueInvoices)->dailyAt('03:20')->onOneServer();
// Horários em UTC: 06h e 09h em Brasília.
Schedule::job(new GenerateRecurringInvoices)->dailyAt('09:00')->onOneServer();
Schedule::job(new SendInvoiceReminders)->dailyAt('12:00')->onOneServer();

// Histórico de alterações com mais de um ano é apagado (config activitylog.delete_records_older_than_days).
Schedule::command('activitylog:clean --force')->dailyAt('03:30')->onOneServer();
