<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\ExampleBusinessException;

uses(RefreshDatabase::class);

it('padroniza o 404 da API', function () {
    $this->getJson('/api/v1/rota-que-nao-existe')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.', 'code' => 'not_found']);
});

it('padroniza o 404 de registro inexistente sem expor o model', function () {
    Route::get('/api/v1/teste-model', fn () => User::query()->findOrFail(999));

    $this->getJson('/api/v1/teste-model')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.', 'code' => 'not_found']);
});

it('padroniza o 403 da API', function () {
    Route::get('/api/v1/teste-policy', fn () => throw new AuthorizationException);
    Route::get('/api/v1/teste-abort', fn () => abort(403));

    foreach (['/api/v1/teste-policy', '/api/v1/teste-abort'] as $uri) {
        $this->getJson($uri)
            ->assertForbidden()
            ->assertExactJson(['message' => 'Você não tem permissão para esta ação.', 'code' => 'forbidden']);
    }
});

it('renderiza erros de regra de negócio com status e código próprios', function () {
    Route::get('/api/v1/teste-negocio', fn () => throw new ExampleBusinessException('Conflito de exemplo.'));

    $this->getJson('/api/v1/teste-negocio')
        ->assertStatus(409)
        ->assertExactJson(['message' => 'Conflito de exemplo.', 'code' => 'example_conflict']);
});
