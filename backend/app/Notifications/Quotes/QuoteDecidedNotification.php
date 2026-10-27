<?php

namespace App\Notifications\Quotes;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa quem criou o orçamento que o cliente aceitou ou recusou.
 */
class QuoteDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly bool $accepted,
        public readonly string $number,
        public readonly string $title,
        public readonly string $clientName,
        public readonly string $quoteUlid,
        public readonly ?string $note = null,
        public readonly ?string $projectUlid = null,
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
        $base = rtrim((string) config('app.frontend_url'), '/');

        if ($this->accepted) {
            return (new MailMessage)
                ->subject("Orçamento {$this->number} aceito por {$this->clientName}")
                ->greeting('Boa notícia!')
                ->line("{$this->clientName} aceitou o orçamento {$this->number} ({$this->title}).")
                ->line('O contrato e o projeto já foram criados no Kairo.')
                ->action('Abrir o projeto', $base.'/projetos/'.$this->projectUlid);
        }

        $message = (new MailMessage)
            ->subject("Orçamento {$this->number} recusado por {$this->clientName}")
            ->greeting('Olá!')
            ->line("{$this->clientName} recusou o orçamento {$this->number} ({$this->title}).");

        if ($this->note !== null) {
            $message->line("Comentário do cliente: \"{$this->note}\"");
        }

        return $message->action('Ver o orçamento', $base.'/orcamentos/'.$this->quoteUlid);
    }
}
