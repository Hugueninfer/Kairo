<?php

namespace App\Support\Pdf;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Gera o PDF da fatura e guarda no disco privado.
 */
class InvoicePdf
{
    public const DISK = 'local';

    public function render(Invoice $invoice): string
    {
        $path = "invoices/{$invoice->ulid}.pdf";
        $output = Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'company' => $invoice->company])
            ->setPaper('a4')
            ->output();

        Storage::disk(self::DISK)->put($path, $output);

        return $path;
    }

    public function exists(?string $path): bool
    {
        return $path !== null && Storage::disk(self::DISK)->exists($path);
    }
}
