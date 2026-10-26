<?php

it('responde o health check da API', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'app' => config('app.name'),
        ]);
});

it('envia os headers de segurança', function () {
    $this->getJson('/api/v1/health')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('libera o CORS com credenciais apenas para o front configurado', function () {
    $this->withHeaders(['Origin' => 'http://localhost:5173'])
        ->getJson('/api/v1/health')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');

    $allowedOrigin = $this->withHeaders(['Origin' => 'https://site-malicioso.com'])
        ->getJson('/api/v1/health')
        ->headers->get('Access-Control-Allow-Origin');

    expect($allowedOrigin)->not->toBe('https://site-malicioso.com');
});

it('entrega o cookie de CSRF para o front', function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});
