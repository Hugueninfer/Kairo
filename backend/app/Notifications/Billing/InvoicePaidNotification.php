<?php

namespace App\Notifications\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa quem emitiu a fatura que ela foi paga por completo.
 */
class InvoicePaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $number,
        public readonly string $clientName,
        public readonly string $total,
        public readonly string $invoiceUlid,
    ) {
        $this->onQueue('mail');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/faturas/'.$this->invoiceUlid;

        return (new MailMessage)
            ->subject("Fatura {$this->number} paga: {$this->total}")
            ->greeting('Pagamento recebido!')
            ->line("{$this->clientName} pagou a fatura {$this->number} ({$this->total}).")
            ->action('Ver a fatura', $url);
    }
}
