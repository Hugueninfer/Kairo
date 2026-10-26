<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('faz login com e-mail e senha corretos', function () {
    $user = User::factory()->create(['email' => 'ana@kairo.test', 'password' => 'SenhaForte123']);

    fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ANA@kairo.test', 'password' => 'SenhaForte123'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user, 'web');
});

it('recusa credenciais inválidas sem dizer qual campo errou', function () {
    User::factory()->create(['email' => 'ana@kairo.test', 'password' => 'SenhaForte123']);

    fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ana@kairo.test', 'password' => 'errada'])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'E-mail ou senha inválidos.', 'code' => 'invalid_credentials']);

    $this->assertGuest('web');
});

it('bloqueia depois de 5 tentativas erradas', function () {
    User::factory()->create(['email' => 'ana@kairo.test', 'password' => 'SenhaForte123']);

    foreach (range(1, 5) as $attempt) {
        fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ana@kairo.test', 'password' => 'errada']);
    }

    fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ana@kairo.test', 'password' => 'SenhaForte123'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_attempts');
});

it('retorna o usuário logado e faz logout', function () {
    $user = User::factory()->create();

    fromSpa($this)->actingAs($user, 'web')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    fromSpa($this)->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->assertGuest('web');
});

it('responde 401 padronizado para quem não está autenticado', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Você precisa estar autenticado.', 'code' => 'unauthenticated']);
});
