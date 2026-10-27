<?php

use App\Jobs\ExpireInvitations;
use App\Jobs\ExpireQuotes;
use App\Jobs\StopForgottenTimers;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireInvitations)->dailyAt('03:00')->onOneServer();
Schedule::job(new StopForgottenTimers)->hourly()->onOneServer();
Schedule::job(new ExpireQuotes)->dailyAt('03:10')->onOneServer();
