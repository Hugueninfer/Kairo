<?php

namespace App\Services\Quotes;

use App\DTOs\Quotes\QuoteData;
use App\DTOs\Quotes\QuoteDecisionData;
use App\DTOs\Quotes\QuoteFilters;
use App\Enums\BillingType;
use App\Enums\ContractStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteItemType;
use App\Enums\QuoteStatus;
use App\Exceptions\Quotes\ClientWithoutEmailException;
use App\Exceptions\Quotes\QuoteExpiredException;
use App\Exceptions\Quotes\QuoteLockedException;
use App\Exceptions\Quotes\QuoteNotOpenException;
use App\Jobs\SendQuoteToClient;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\Quotes\QuoteDecidedNotification;
use App\Notifications\Quotes\QuoteSentNotification;
use App\Repositories\Contracts\ClientRepository;
use App\Repositories\Contracts\ContractRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Repositories\Contracts\QuoteRepository;
use App\Support\Money;
use App\Support\Pdf\QuotePdf;
use App\Support\PublicLinks;
use App\Support\TenantContext;
use App\Support\Transactions\TransactionManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;

/**
 * Orçamentos: rascunho, envio ao cliente (PDF + link público), resposta do cliente e
 * criação automática do contrato e do projeto no aceite.
 */
class QuoteService
{
    public function __construct(
        private readonly QuoteRepository $quotes,
        private readonly ClientRepository $clients,
        private readonly ProjectRepository $projects,
        private readonly ContractRepository $contracts,
        private readonly QuoteCalculator $calculator,
        private readonly QuotePdf $pdf,
        private readonly PublicLinks $links,
        private readonly TenantContext $tenant,
        private readonly TransactionManager $transaction,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Quote>
     */
    public function list(QuoteFilters $filters): LengthAwarePaginator
    {
        return $this->quotes->paginateFiltered(
            $filters->search,
            $filters->status,
            $filters->client === null ? null : $this->client($filters->client)->id,
            $filters->perPage,
        );
    }

    public function show(Quote $quote): Quote
    {
        return $this->quotes->loadDetails($quote);
    }

    public function create(User $author, QuoteData $data): Quote
    {
        $client = $this->client($data->client);
        ['items' => $items, 'totals' => $totals] = $this->calculator->calculate($data);
        $company = $this->tenant->company();

        return $this->transaction->run(fn (): Quote => $this->quotes->createQuote([
            ...$this->fields($data),
            ...$totals,
            'client_id' => $client->id,
            'created_by' => $author->id,
            'number' => $this->quotes->nextNumber($company->id, now($company->timezone)->year),
            'status' => QuoteStatus::Draft,
        ], $items));
    }

    /**
     * Editar um orçamento já enviado o devolve para rascunho: o cliente só responde
     * depois de receber a versão nova.
     */
    public function update(Quote $quote, QuoteData $data): Quote
    {
        $this->ensureNotDecided($quote);
        $client = $this->client($data->client);
        ['items' => $items, 'totals' => $totals] = $this->calculator->calculate($data);

        return $this->quotes->updateQuote($quote, [
            ...$this->fields($data),
            ...$totals,
            'client_id' => $client->id,
            'status' => QuoteStatus::Draft,
            'viewed_at' => null,
            'pdf_path' => null,
        ], $items);
    }

    public function delete(Quote $quote): void
    {
        if ($quote->status === QuoteStatus::Accepted) {
            throw new QuoteLockedException;
        }

        $this->quotes->deleteQuote($quote);
    }

    /**
     * Cria um rascunho novo com os mesmos itens e uma validade nova.
     */
    public function duplicate(User $author, Quote $quote): Quote
    {
        $quote = $this->quotes->loadDetails($quote);
        $company = $this->tenant->company();
        $validity = max(1, (int) $quote->created_at?->copy()->startOfDay()->diffInDays($quote->valid_until));

        return $this->transaction->run(fn (): Quote => $this->quotes->createQuote([
            ...$quote->only(['client_id', 'title', 'introduction', 'terms', 'subtotal', 'discount', 'tax_rate', 'tax_amount', 'total']),
            'created_by' => $author->id,
            'number' => $this->quotes->nextNumber($company->id, now($company->timezone)->year),
            'status' => QuoteStatus::Draft,
            'valid_until' => now($company->timezone)->addDays($validity)->toDateString(),
        ], $quote->items->map(fn ($item) => $item->only(['position', 'description', 'type', 'quantity', 'unit_price', 'total']))->values()->all()));
    }

    /**
     * Marca como enviado e põe na fila a geração do PDF e o e-mail ao cliente.
     * Reenviar (ex.: o cliente perdeu o e-mail) é permitido enquanto não houver resposta.
     */
    public function send(Quote $quote): Quote
    {
        $this->ensureNotDecided($quote);
        $quote = $this->quotes->loadDetails($quote);

        if (blank($quote->client->email)) {
            throw new ClientWithoutEmailException;
        }

        if ($this->isPastValidity($quote, $this->tenant->company()->timezone)) {
            throw new QuoteExpiredException;
        }

        return $this->transaction->run(function () use ($quote): Quote {
            $quote = $this->quotes->updateQuote($quote, [
                'status' => $quote->status === QuoteStatus::Viewed ? QuoteStatus::Viewed : QuoteStatus::Sent,
                'sent_at' => now(),
            ]);

            SendQuoteToClient::dispatch($quote->ulid);

            return $quote;
        });
    }

    /**
     * Chamado pela fila: gera o PDF e manda o e-mail ao cliente.
     */
    public function deliver(string $ulid): void
    {
        $quote = $this->quotes->findForPublic($ulid);

        if ($quote === null || ! $quote->status->awaitsDecision() || blank($quote->client->email)) {
            return;
        }

        $path = $this->pdfFor($quote);
        $company = $quote->company;

        Notification::route('mail', [(string) $quote->client->email => $quote->client->name])->notify(new QuoteSentNotification(
            companyName: $company->name,
            number: $quote->number,
            title: $quote->title,
            total: Money::format($quote->total, $company->currency),
            validUntil: $quote->valid_until->format('d/m/Y'),
            url: $this->links->quote($quote),
            pdfPath: $path,
        ));
    }

    /**
     * Caminho do PDF no disco, gerando na hora se ainda não existir.
     */
    public function pdf(Quote $quote): string
    {
        $quote = $this->quotes->loadDetails($quote)->setRelation('company', $this->tenant->company());

        return $this->pdfFor($quote);
    }

    public function expireOverdue(): int
    {
        // Roda às 03h UTC (meia-noite em Brasília): o que venceu até ontem expira.
        return $this->quotes->expireOverdue(now()->toDateString());
    }

    /**
     * Orçamento aberto pelo link público. Define a empresa do orçamento como ativa
     * (a rota é pública, sem empresa na sessão) e registra a primeira visualização.
     */
    public function openPublic(string $ulid): Quote
    {
        $quote = $this->quotes->findForPublic($ulid);

        if ($quote === null || $quote->status === QuoteStatus::Draft) {
            throw (new ModelNotFoundException)->setModel(Quote::class, [$ulid]);
        }

        $this->tenant->set($quote->company);

        if ($quote->status === QuoteStatus::Sent) {
            $this->quotes->updateQuote($quote, ['status' => QuoteStatus::Viewed, 'viewed_at' => now()]);
        }

        return $quote;
    }

    /**
     * Aceite ou recusa do cliente. No aceite, cria o projeto e o contrato na mesma transação.
     */
    public function decide(string $ulid, QuoteDecisionData $decision): Quote
    {
        $quote = $this->openPublic($ulid);
        $company = $quote->company;

        $decided = $this->transaction->run(function () use ($quote, $decision, $company): Quote {
            $locked = $this->quotes->lockForDecision($quote);

            if (! $locked->status->awaitsDecision()) {
                throw new QuoteNotOpenException;
            }

            if ($this->isPastValidity($locked, $company->timezone)) {
                throw new QuoteExpiredException;
            }

            $attributes = [
                'decided_at' => now(),
                'signer_ip' => $decision->ip,
                'decision_note' => $decision->note,
            ];

            if (! $decision->accept) {
                return $this->quotes->updateQuote($quote, [...$attributes, 'status' => QuoteStatus::Rejected]);
            }

            $project = $this->createProject($quote);
            $year = now($company->timezone)->year;

            $this->contracts->createContract([
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'project_id' => $project->id,
                'number' => $this->contracts->nextNumber($company->id, $year),
                'title' => $quote->title,
                'total' => $quote->total,
                'terms' => $quote->terms,
                'status' => ContractStatus::Active,
                'signed_at' => now(),
                'signer_name' => $decision->signer_name,
                'signer_ip' => $decision->ip,
            ]);

            return $this->quotes->updateQuote($quote, [
                ...$attributes,
                'status' => QuoteStatus::Accepted,
                'signer_name' => $decision->signer_name,
                'project_id' => $project->id,
            ]);
        });

        $this->notifyDecision($decided, $quote->creator ?? $company->owner);

        return $decided;
    }

    /**
     * Projeto por hora se todos os itens forem por hora (com o valor/hora do primeiro item);
     * senão, preço fechado. O total do orçamento vira o orçamento do projeto.
     */
    private function createProject(Quote $quote): Project
    {
        $hourly = $quote->items->every(fn ($item) => $item->type === QuoteItemType::Hourly);

        return $this->projects->createProject([
            'client_id' => $quote->client_id,
            'name' => $quote->title,
            'description' => $quote->introduction,
            'status' => ProjectStatus::Active,
            'billing_type' => $hourly ? BillingType::Hourly : BillingType::Fixed,
            'hourly_rate' => $hourly ? $quote->items->first()?->unit_price : null,
            'budget' => $quote->total,
            'starts_on' => now($quote->company->timezone)->toDateString(),
        ]);
    }

    private function notifyDecision(Quote $quote, User $recipient): void
    {
        $recipient->notify(new QuoteDecidedNotification(
            accepted: $quote->status === QuoteStatus::Accepted,
            number: $quote->number,
            title: $quote->title,
            clientName: $quote->client->name,
            quoteUlid: $quote->ulid,
            note: $quote->decision_note,
            projectUlid: $quote->project?->ulid,
        ));
    }

    private function pdfFor(Quote $quote): string
    {
        if ($this->pdf->exists($quote->pdf_path)) {
            return (string) $quote->pdf_path;
        }

        $path = $this->pdf->render($quote);
        $this->quotes->updateQuote($quote, ['pdf_path' => $path]);

        return $path;
    }

    /**
     * A validade vale até o fim do dia, no fuso da empresa.
     */
    private function isPastValidity(Quote $quote, string $timezone): bool
    {
        return $quote->valid_until->toDateString() < now($timezone)->toDateString();
    }

    private function ensureNotDecided(Quote $quote): void
    {
        if ($quote->status->isDecided()) {
            throw new QuoteLockedException;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(QuoteData $data): array
    {
        return [
            'title' => $data->title,
            'introduction' => $data->introduction,
            'terms' => $data->terms,
            'valid_until' => $data->valid_until,
        ];
    }

    private function client(string $ulid): Client
    {
        return $this->clients->findByUlid($ulid) ?? throw (new ModelNotFoundException)->setModel(Client::class, [$ulid]);
    }
}
