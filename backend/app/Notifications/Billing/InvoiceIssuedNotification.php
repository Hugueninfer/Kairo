<?php

namespace App\Notifications\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * E-mail ao cliente com o link de pagamento e o PDF da fatura.
 */
class InvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $companyName,
        public readonly string $number,
        public readonly string $total,
        public readonly string $dueDate,
        public readonly string $paymentUrl,
        public readonly string $pdfPath,
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
        return (new MailMessage)
            ->subject("Fatura {$this->number} de {$this->companyName}: {$this->total}")
            ->greeting('Olá!')
            ->line("{$this->companyName} enviou a fatura {$this->number}, no valor de {$this->total}, com vencimento em {$this->dueDate}.")
            ->action('Pagar com Pix, boleto ou cartão', $this->paymentUrl)
            ->line('O PDF da fatura segue em anexo.')
            ->attach(Storage::disk('local')->path($this->pdfPath), [
                'as' => "fatura-{$this->number}.pdf",
                'mime' => 'application/pdf',
            ]);
    }
}
