<?php

namespace App\Repositories\Eloquent;

use App\Criteria\DocumentFilterCriteria;
use App\Criteria\SearchCriteria;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Repositories\Contracts\QuoteRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class QuoteRepositoryEloquent extends BaseRepository implements QuoteRepository
{
    public function model(): string
    {
        return Quote::class;
    }

    public function paginateFiltered(?string $search, ?QuoteStatus $status, ?int $clientId, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Quote> */
        return $this->filteredQuery([
            new SearchCriteria($search, ['title', 'number']),
            new DocumentFilterCriteria($status, $clientId),
        ])
            ->with('client:id,ulid,name,email')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUlid(string $ulid): ?Quote
    {
        /** @var Quote|null */
        return $this->filteredQuery()->where('ulid', $ulid)->first();
    }

    public function findForPublic(string $ulid): ?Quote
    {
        return Quote::query()
            ->withoutGlobalScope('company')
            ->with(['company.owner', 'client', 'items', 'creator'])
            ->where('ulid', $ulid)
            ->first();
    }

    public function loadDetails(Quote $quote): Quote
    {
        return $quote->load([
            'client:id,ulid,name,email,document,deleted_at',
            'items',
            'project:id,ulid,name',
            'contract:id,ulid,quote_id,number,status',
        ]);
    }

    public function lockForDecision(Quote $quote): Quote
    {
        /** @var Quote */
        return Quote::query()->withoutGlobalScope('company')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
    }

    public function nextNumber(int $companyId, int $year): string
    {
        return $this->nextSequence($companyId, "ORC-{$year}-");
    }

    public function createQuote(array $attributes, array $items): Quote
    {
        return DB::transaction(function () use ($attributes, $items): Quote {
            $quote = Quote::query()->create($attributes);
            $quote->items()->createMany($items);

            return $this->loadDetails($quote);
        });
    }

    public function updateQuote(Quote $quote, array $attributes, ?array $items = null): Quote
    {
        return DB::transaction(function () use ($quote, $attributes, $items): Quote {
            $quote->update($attributes);

            if ($items !== null) {
                $quote->items()->delete();
                $quote->items()->createMany($items);
                $quote->unsetRelation('items');
            }

            return $this->loadDetails($quote);
        });
    }

    public function deleteQuote(Quote $quote): void
    {
        $quote->delete();
    }

    public function expireOverdue(string $today): int
    {
        return Quote::query()
            ->withoutGlobalScope('company')
            ->whereIn('status', [QuoteStatus::Sent, QuoteStatus::Viewed])
            ->whereDate('valid_until', '<', $today)
            ->update(['status' => QuoteStatus::Expired, 'updated_at' => now()]);
    }
}
