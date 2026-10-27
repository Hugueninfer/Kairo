@php($money = fn ($value) => \App\Support\Money::format($value, $company->currency))
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Orçamento {{ $quote->number }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        table { width: 100%; border-collapse: collapse; }
        .items th { text-align: left; font-size: 10px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 6px 4px; }
        .items td { border-bottom: 1px solid #e2e8f0; padding: 7px 4px; }
        .right, .items th.right { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { padding: 3px 4px; }
        .grand td { font-size: 14px; font-weight: bold; border-top: 1px solid #cbd5e1; padding-top: 6px; }
        .section { margin-top: 20px; }
        .section h2 { font-size: 12px; margin: 0 0 4px; }
        .text { white-space: pre-line; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $company->name }}</h1>
                @if ($company->document)<div class="muted">{{ $company->document }}</div>@endif
            </td>
            <td class="right">
                <div style="font-size: 16px; font-weight: bold;">Orçamento {{ $quote->number }}</div>
                <div class="muted">Emitido em {{ ($quote->sent_at ?? $quote->created_at)?->timezone($company->timezone)->format('d/m/Y') }}</div>
                <div class="muted">Válido até {{ $quote->valid_until->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <p><strong>Para:</strong> {{ $quote->client->name }}@if ($quote->client->document) ({{ $quote->client->document }})@endif</p>
    <h2 style="font-size: 15px; margin: 10px 0;">{{ $quote->title }}</h2>

    @if ($quote->introduction)
        <p class="text">{{ $quote->introduction }}</p>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th>Descrição</th>
                <th class="right">Qtd.</th>
                <th class="right">Valor unitário</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ \App\Support\Money::quantity($item->quantity) }} {{ $item->type->unit() }}</td>
                    <td class="right">{{ $money($item->unit_price) }}</td>
                    <td class="right">{{ $money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $money($quote->subtotal) }}</td></tr>
        @if ((float) $quote->discount > 0)
            <tr><td>Desconto</td><td class="right">− {{ $money($quote->discount) }}</td></tr>
        @endif
        @if ((float) $quote->tax_rate > 0)
            <tr><td>Impostos ({{ \App\Support\Money::quantity($quote->tax_rate) }}%)</td><td class="right">{{ $money($quote->tax_amount) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="right">{{ $money($quote->total) }}</td></tr>
    </table>

    @if ($quote->terms)
        <div class="section">
            <h2>Condições</h2>
            <div class="text">{{ $quote->terms }}</div>
        </div>
    @endif

    <p class="muted section">Para aceitar ou recusar, use o link enviado por e-mail.</p>
</body>
</html>
