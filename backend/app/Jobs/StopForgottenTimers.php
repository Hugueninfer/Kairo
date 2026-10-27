<?php

namespace App\Jobs;

use App\Notifications\Timesheet\ForgottenTimerNotification;
use App\Services\Timesheet\TimeEntryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Roda de hora em hora: para timers ligados há mais de MAX_HOURS e avisa a pessoa por e-mail.
 */
class StopForgottenTimers implements ShouldQueue
{
    use Queueable;

    public const MAX_HOURS = 10;

    public function handle(TimeEntryService $entries): void
    {
        $stopped = $entries->stopForgotten(self::MAX_HOURS);

        foreach ($stopped as $entry) {
            $entry->user->notify(new ForgottenTimerNotification($entry->project->name, self::MAX_HOURS));
        }

        if ($stopped !== []) {
            Log::info('Timers esquecidos parados', ['total' => count($stopped)]);
        }
    }
}
