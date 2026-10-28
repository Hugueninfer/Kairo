<?php

namespace App\Http\Controllers\Api\V1\Quotes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\QuoteDecisionRequest;
use App\Http\Resources\PublicQuoteResource;
use App\Services\Quotes\QuoteService;
use Dedoc\Scramble\Attributes\Group;

/**
 * Orçamento aberto pelo cliente, sem login. A rota exige o link assinado (middleware signed).
 */
#[Group('Links públicos', weight: 17)]
class PublicQuoteController extends Controller
{
    public function __construct(private readonly QuoteService $quotes) {}

    public function show(string $quote): PublicQuoteResource
    {
        return PublicQuoteResource::make($this->quotes->openPublic($quote));
    }

    public function decide(QuoteDecisionRequest $request, string $quote): PublicQuoteResource
    {
        return PublicQuoteResource::make($this->quotes->decide($quote, $request->toData()));
    }
}
