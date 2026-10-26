<?php

use App\Jobs\ExpireInvitations;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireInvitations)->dailyAt('03:00')->onOneServer();
