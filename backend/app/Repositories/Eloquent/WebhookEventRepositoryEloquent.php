<?php

namespace App\Repositories\Eloquent;

use App\Models\WebhookEvent;
use App\Repositories\Contracts\WebhookEventRepository;

class WebhookEventRepositoryEloquent extends BaseRepository implements WebhookEventRepository
{
    public function model(): string
    {
        return WebhookEvent::class;
    }

    public function storeOnce(string $gateway, string $eventId, string $type, array $payload): ?WebhookEvent
    {
        // createOrFirst usa o índice único: dois avisos iguais ao mesmo tempo não criam duas linhas.
        $event = WebhookEvent::query()->createOrFirst(
            ['gateway' => $gateway, 'event_id' => $eventId],
            ['type' => $type, 'payload' => $payload],
        );

        return $event->wasRecentlyCreated ? $event : null;
    }

    public function findById(int $id): ?WebhookEvent
    {
        return WebhookEvent::query()->find($id);
    }

    public function markProcessed(WebhookEvent $event, ?string $error = null): void
    {
        $event->update(['processed_at' => now(), 'error' => $error]);
    }
}
