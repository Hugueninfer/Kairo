<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->onQueue('mail');
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Redefinição de senha do Kairo')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $this->resetUrl($notifiable))
            ->line("Este link expira em {$minutes} minutos.")
            ->line('Se você não pediu a redefinição, nenhuma ação é necessária.');
    }
}
