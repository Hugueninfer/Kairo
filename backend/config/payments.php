<?php

return [
    /*
    | Gateway usado nas faturas novas. "fake" simula o pagamento e só funciona fora de produção.
    */
    'default' => env('PAYMENT_GATEWAY', 'fake'),

    'gateways' => [
        'fake' => [
            'webhook_token' => env('FAKE_GATEWAY_WEBHOOK_TOKEN', 'kairo-fake-token'),
        ],
    ],
];
