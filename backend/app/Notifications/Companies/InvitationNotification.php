<?php

namespace App\Notifications\Companies;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $companyName,
        public readonly string $inviterName,
        public readonly string $token,
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
        $url = rtrim((string) config('app.frontend_url'), '/').'/convite/'.$this->token;

        return (new MailMessage)
            ->subject("{$this->inviterName} convidou você para {$this->companyName} no Kairo")
            ->greeting('Olá!')
            ->line("{$this->inviterName} convidou você para fazer parte da empresa {$this->companyName} no Kairo.")
            ->action('Aceitar convite', $url)
            ->line('O convite vale por 7 dias. Se você não esperava este e-mail, pode ignorá-lo.');
    }
}
