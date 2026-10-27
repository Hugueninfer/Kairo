<?php

namespace App\Http\Controllers\Dev;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Integrations\Payments\Data\GatewayEvent;
use App\Services\Billing\PaymentWebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Página de pagamento do gateway simulado (só fora de produção). Pagar aqui gera o
 * mesmo aviso que um gateway real mandaria para o webhook.
 */
class FakeCheckoutController extends Controller
{
    public function show(Request $request, string $charge): View
    {
        abort_if(app()->isProduction(), 404);

        return view('dev.fake-checkout', [
            'charge' => $charge,
            'amount' => (string) $request->query('amount', '0'),
            'paid' => $request->boolean('paid'),
            'methods' => [PaymentMethod::Pix, PaymentMethod::Boleto, PaymentMethod::CreditCard],
        ]);
    }

    public function pay(Request $request, string $charge, PaymentWebhookService $webhooks): RedirectResponse
    {
        abort_if(app()->isProduction(), 404);
        $method = PaymentMethod::tryFrom((string) $request->input('method')) ?? PaymentMethod::Pix;
        $payload = [
            'id' => 'evt_'.Str::lower(Str::ulid()->toBase32()),
            'event' => 'payment.paid',
            'charge' => $charge,
            'payment' => 'pay_'.Str::lower(Str::ulid()->toBase32()),
            'amount' => (string) $request->input('amount'),
            'method' => $method->value,
        ];

        $webhooks->accept('fake', new GatewayEvent(
            id: $payload['id'],
            type: $payload['event'],
            payload: $payload,
            confirmsPayment: true,
            chargeId: $charge,
            paymentId: $payload['payment'],
            amount: number_format((float) $payload['amount'], 2, '.', ''),
            method: $method,
            paidAt: Carbon::now(),
        ));

        return redirect()->route('fake-checkout.show', ['charge' => $charge, 'amount' => $payload['amount'], 'paid' => 1]);
    }
}
