<?php

namespace App\Support\Pdf;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Gera o PDF do orçamento e guarda no disco privado (nunca em public/).
 */
class QuotePdf
{
    public const DISK = 'local';

    /**
     * @return string caminho no disco
     */
    public function render(Quote $quote): string
    {
        $path = "quotes/{$quote->ulid}.pdf";
        $output = Pdf::loadView('pdf.quote', ['quote' => $quote, 'company' => $quote->company])
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
