<?php

namespace App\Jobs;

use App\Services\Companies\InvitationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Roda todo dia pelo Scheduler e marca como expirados os convites vencidos.
 */
class ExpireInvitations implements ShouldQueue
{
    use Queueable;

    public function handle(InvitationService $invitations): void
    {
        $expired = $invitations->expireOverdue();

        if ($expired > 0) {
            Log::info('Convites expirados', ['total' => $expired]);
        }
    }
}
