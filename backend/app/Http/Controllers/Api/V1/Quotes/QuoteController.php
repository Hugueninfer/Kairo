<?php

namespace App\Http\Controllers\Api\V1\Quotes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\ListQuotesRequest;
use App\Http\Requests\Quotes\QuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteService;
use App\Support\Pdf\QuotePdf;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Orçamentos', weight: 10)]
class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $quotes) {}

    public function index(ListQuotesRequest $request): AnonymousResourceCollection
    {
        return QuoteResource::collection($this->quotes->list($request->toFilters()));
    }

    public function show(Quote $quote): QuoteResource
    {
        return QuoteResource::make($this->quotes->show($quote));
    }

    public function store(QuoteRequest $request): JsonResponse
    {
        return QuoteResource::make($this->quotes->create($this->user($request), $request->toData()))->response()->setStatusCode(201);
    }

    public function update(QuoteRequest $request, Quote $quote): QuoteResource
    {
        return QuoteResource::make($this->quotes->update($quote, $request->toData()));
    }

    public function destroy(Quote $quote): Response
    {
        $this->quotes->delete($quote);

        return response()->noContent();
    }

    public function send(Quote $quote): QuoteResource
    {
        return QuoteResource::make($this->quotes->send($quote));
    }

    public function duplicate(Request $request, Quote $quote): JsonResponse
    {
        return QuoteResource::make($this->quotes->duplicate($this->user($request), $quote))->response()->setStatusCode(201);
    }

    public function pdf(Quote $quote): StreamedResponse
    {
        return Storage::disk(QuotePdf::DISK)->download($this->quotes->pdf($quote), "orcamento-{$quote->number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
