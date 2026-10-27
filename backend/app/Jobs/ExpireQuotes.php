<?php

namespace App\Jobs;

use App\Services\Quotes\QuoteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Roda todo dia pelo Scheduler e marca como expirados os orçamentos vencidos sem resposta.
 */
class ExpireQuotes implements ShouldQueue
{
    use Queueable;

    public function handle(QuoteService $quotes): void
    {
        $expired = $quotes->expireOverdue();

        if ($expired > 0) {
            Log::info('Orçamentos expirados', ['total' => $expired]);
        }
    }
}
