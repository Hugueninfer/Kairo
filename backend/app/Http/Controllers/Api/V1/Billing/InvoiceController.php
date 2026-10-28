<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ContractInvoiceRequest;
use App\Http\Requests\Billing\HoursInvoiceRequest;
use App\Http\Requests\Billing\InvoiceRequest;
use App\Http\Requests\Billing\ListInvoicesRequest;
use App\Http\Requests\Billing\PaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use App\Support\Pdf\InvoicePdf;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Faturas', weight: 12)]
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(ListInvoicesRequest $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection($this->invoices->list($request->toFilters()))
            ->additional(['summary' => $this->invoices->summary()]);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->invoices->show($invoice));
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        return $this->created($this->invoices->create($this->user($request), $request->toData()));
    }

    public function fromHours(HoursInvoiceRequest $request): JsonResponse
    {
        return $this->created($this->invoices->createFromHours($this->user($request), $request->toData()));
    }

    public function fromContract(ContractInvoiceRequest $request): JsonResponse
    {
        return $this->created($this->invoices->createFromContract($this->user($request), $request->toData()));
    }

    public function update(InvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->invoices->update($invoice, $request->toData()));
    }

    public function destroy(Invoice $invoice): Response
    {
        $this->invoices->delete($invoice);

        return response()->noContent();
    }

    public function send(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->invoices->send($invoice));
    }

    public function cancel(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->invoices->cancel($invoice));
    }

    public function recordPayment(PaymentRequest $request, Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($this->invoices->recordPayment($this->user($request), $invoice, $request->toData()));
    }

    public function pdf(Invoice $invoice): StreamedResponse
    {
        return Storage::disk(InvoicePdf::DISK)->download($this->invoices->pdf($invoice), "fatura-{$invoice->number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function created(Invoice $invoice): JsonResponse
    {
        return InvoiceResource::make($invoice)->response()->setStatusCode(201);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
