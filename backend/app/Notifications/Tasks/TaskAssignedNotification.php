<?php

namespace App\Notifications\Tasks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa por e-mail quem recebeu uma tarefa. Leva só dados prontos (sem models),
 * então o job da fila não depende do contexto de empresa para montar o e-mail.
 */
class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $taskTitle,
        public readonly string $projectName,
        public readonly string $projectUlid,
        public readonly string $companyName,
        public readonly string $assignerName,
        public readonly ?string $dueOn = null,
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
        $url = rtrim((string) config('app.frontend_url'), '/').'/projetos/'.$this->projectUlid;

        $message = (new MailMessage)
            ->subject("Nova tarefa para você: {$this->taskTitle}")
            ->greeting('Olá!')
            ->line("{$this->assignerName} atribuiu a você a tarefa \"{$this->taskTitle}\" no projeto {$this->projectName} ({$this->companyName}).");

        if ($this->dueOn !== null) {
            $message->line("Prazo: {$this->dueOn}.");
        }

        return $message->action('Abrir o projeto', $url);
    }
}
