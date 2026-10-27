<?php

namespace App\Notifications\Timesheet;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgottenTimerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $projectName,
        public readonly int $hours,
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
        $url = rtrim((string) config('app.frontend_url'), '/').'/horas';

        return (new MailMessage)
            ->subject('Seu timer ficou ligado')
            ->greeting('Olá!')
            ->line("O timer do projeto {$this->projectName} ficou ligado por mais de {$this->hours} horas, então o Kairo parou ele.")
            ->line("O apontamento foi fechado com {$this->hours} horas. Ajuste o horário se precisar.")
            ->action('Ver minhas horas', $url);
    }
}
