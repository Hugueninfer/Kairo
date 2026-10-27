<?php

namespace App\Repositories\Contracts;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Contracts\RepositoryInterface;

interface QuoteRepository extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Quote>
     */
    public function paginateFiltered(?string $search, ?QuoteStatus $status, ?int $clientId, int $perPage): LengthAwarePaginator;

    public function findByUlid(string $ulid): ?Quote;

    /**
     * Busca pelo ULID sem depender da empresa ativa: usado pelo link público e pela fila.
     * Já carrega empresa, cliente, itens e quem criou.
     */
    public function findForPublic(string $ulid): ?Quote;

    /**
     * Carrega itens, cliente, projeto e contrato.
     */
    public function loadDetails(Quote $quote): Quote;

    /**
     * Relê o orçamento travando a linha, para dois cliques no aceite não criarem dois contratos.
     */
    public function lockForDecision(Quote $quote): Quote;

    /**
     * Próximo número da empresa no ano: ORC-2026-0001. Precisa rodar numa transação.
     */
    public function nextNumber(int $companyId, int $year): string;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createQuote(array $attributes, array $items): Quote;

    /**
     * Atualiza o orçamento. Com $items, troca todos os itens pelos novos.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>|null  $items
     */
    public function updateQuote(Quote $quote, array $attributes, ?array $items = null): Quote;

    public function deleteQuote(Quote $quote): void;

    /**
     * Marca como expirados os orçamentos enviados com validade antes de $today. Devolve quantos.
     */
    public function expireOverdue(string $today): int;
}
