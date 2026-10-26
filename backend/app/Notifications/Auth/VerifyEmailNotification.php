<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('mail');
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirme seu e-mail no Kairo')
            ->greeting('Olá!')
            ->line('Clique no botão abaixo para confirmar seu e-mail e ativar sua conta.')
            ->action('Confirmar e-mail', $this->verificationUrl($notifiable))
            ->line('Se você não criou uma conta no Kairo, pode ignorar este e-mail.');
    }
}
