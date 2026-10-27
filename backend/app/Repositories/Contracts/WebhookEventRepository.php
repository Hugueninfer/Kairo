<?php

namespace App\Repositories\Contracts;

use App\Models\WebhookEvent;
use Prettus\Repository\Contracts\RepositoryInterface;

interface WebhookEventRepository extends RepositoryInterface
{
    /**
     * Guarda o aviso. Devolve null se o mesmo aviso (gateway + id) já tinha chegado antes.
     *
     * @param  array<string, mixed>  $payload
     */
    public function storeOnce(string $gateway, string $eventId, string $type, array $payload): ?WebhookEvent;

    public function findById(int $id): ?WebhookEvent;

    public function markProcessed(WebhookEvent $event, ?string $error = null): void;
}
