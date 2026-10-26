<?php

use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Eloquent\UserRepositoryEloquent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolve o contrato para a implementação Eloquent', function () {
    expect(app(UserRepository::class))->toBeInstanceOf(UserRepositoryEloquent::class);
});

it('encontra um usuário pelo e-mail', function () {
    $user = User::factory()->create(['email' => 'ana@kairo.test']);

    $found = app(UserRepository::class)->findByEmail('ana@kairo.test');

    expect($found?->is($user))->toBeTrue();
});

it('retorna null quando o e-mail não existe', function () {
    expect(app(UserRepository::class)->findByEmail('ninguem@kairo.test'))->toBeNull();
});
