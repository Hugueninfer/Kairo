<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagamento simulado</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #1e293b; display: flex; justify-content: center; padding: 48px 16px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 32px; max-width: 380px; width: 100%; }
        .tag { display: inline-block; background: #fef3c7; color: #92400e; font-size: 12px; padding: 2px 8px; border-radius: 6px; }
        .amount { font-size: 32px; font-weight: 700; margin: 16px 0 24px; }
        button { width: 100%; padding: 12px; margin-bottom: 8px; border: 0; border-radius: 10px; background: #059669; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
        .ok { background: #ecfdf5; color: #065f46; padding: 16px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="tag">Gateway simulado: nenhum dinheiro é cobrado</span>
        <div class="amount">R$ {{ number_format((float) $amount, 2, ',', '.') }}</div>
        @if ($paid)
            <div class="ok" data-test="paid">Pagamento confirmado. O Kairo já recebeu o aviso e vai dar baixa na fatura.</div>
        @else
            <form method="post" action="{{ route('fake-checkout.pay', ['charge' => $charge]) }}">
                @csrf
                <input type="hidden" name="amount" value="{{ $amount }}">
                @foreach ($methods as $method)
                    <button type="submit" name="method" value="{{ $method->value }}">Pagar com {{ $method->label() }}</button>
                @endforeach
            </form>
        @endif
    </div>
</body>
</html>
