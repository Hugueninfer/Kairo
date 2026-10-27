<?php

namespace App\Notifications\Quotes;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * E-mail ao cliente com o PDF anexo e o link público para aceitar ou recusar.
 */
class QuoteSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $companyName,
        public readonly string $number,
        public readonly string $title,
        public readonly string $total,
        public readonly string $validUntil,
        public readonly string $url,
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
            ->subject("Orçamento {$this->number} de {$this->companyName}: {$this->title}")
            ->greeting('Olá!')
            ->line("{$this->companyName} enviou um orçamento para você: {$this->title}.")
            ->line("Valor total: {$this->total}. Válido até {$this->validUntil}.")
            ->action('Ver e responder', $this->url)
            ->line('O PDF segue em anexo. Pelo link você pode aceitar ou recusar a proposta.')
            ->attach(Storage::disk('local')->path($this->pdfPath), [
                'as' => "orcamento-{$this->number}.pdf",
                'mime' => 'application/pdf',
            ]);
    }
}
