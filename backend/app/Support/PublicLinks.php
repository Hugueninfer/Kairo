<?php

namespace App\Support;

use App\Models\Quote;
use Illuminate\Support\Facades\URL;

/**
 * Links públicos enviados a clientes (sem login), assinados e com prazo.
 *
 * A assinatura é da rota da API, relativa (sem domínio), porque o front e a API podem
 * estar em hosts diferentes. O link do e-mail aponta para a tela do front, que repassa
 * expires e signature para a API.
 */
class PublicLinks
{
    /** Depois da validade, o link ainda abre por este tempo para o cliente consultar. */
    public const QUOTE_GRACE_DAYS = 30;

    public function quote(Quote $quote): string
    {
        $signed = URL::temporarySignedRoute(
            'api.v1.public.quotes.show',
            $quote->valid_until->copy()->endOfDay()->addDays(self::QUOTE_GRACE_DAYS),
            ['quote' => $quote->ulid],
            absolute: false,
        );

        return rtrim((string) config('app.frontend_url'), '/').'/orcamento/'.$quote->ulid.'?'.(string) parse_url($signed, PHP_URL_QUERY);
    }
}
