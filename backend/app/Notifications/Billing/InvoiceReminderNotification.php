<?php

namespace App\Notifications\Billing;

use App\Enums\ReminderStage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lembrete ao cliente de uma fatura em aberto (antes, no dia ou depois do vencimento).
 */
class InvoiceReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ReminderStage $stage,
        public readonly string $companyName,
        public readonly string $number,
        public readonly string $balance,
        public readonly string $dueDate,
        public readonly string $paymentUrl,
        public readonly ?string $customMessage = null,
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
        $message = (new MailMessage)
            ->subject($this->stage->subject($this->number, $this->companyName))
            ->greeting('Olá!')
            ->line($this->summary());

        if (filled($this->customMessage)) {
            $message->line((string) $this->customMessage);
        }

        return $message
            ->action('Pagar com Pix, boleto ou cartão', $this->paymentUrl)
            ->line('Se você já pagou, desconsidere este e-mail.');
    }

    private function summary(): string
    {
        $invoice = "a fatura {$this->number} de {$this->companyName}, no valor de {$this->balance}";

        return match ($this->stage) {
            ReminderStage::BeforeDue => "Passando para lembrar que {$invoice}, vence em {$this->dueDate}.",
            ReminderStage::DueToday => "Hoje ({$this->dueDate}) é o vencimento d{$invoice}.",
            ReminderStage::Overdue3, ReminderStage::Overdue7 => "Ainda não identificamos o pagamento d{$invoice}, vencida em {$this->dueDate}.",
        };
    }
}
