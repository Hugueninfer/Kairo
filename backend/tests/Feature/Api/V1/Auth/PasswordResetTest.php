<?php

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

it('envia o link de redefinição apontando para o front', function () {
    $user = User::factory()->create(['email' => 'ana@kairo.test']);

    fromSpa($this)->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@kairo.test'])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'http://localhost:5173/redefinir-senha?token=');
    });
});

it('responde igual para e-mail que não existe', function () {
    fromSpa($this)->postJson('/api/v1/auth/forgot-password', ['email' => 'ninguem@kairo.test'])
        ->assertOk()
        ->assertJsonPath('message', 'Se o e-mail estiver cadastrado, você vai receber um link para redefinir a senha.');

    Notification::assertNothingSent();
});

it('redefine a senha com um token válido', function () {
    $user = User::factory()->create(['email' => 'ana@kairo.test']);
    $token = Password::createToken($user);

    fromSpa($this)->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'ana@kairo.test',
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])->assertOk();

    expect(Hash::check('NovaSenha123', $user->fresh()->password))->toBeTrue();
});

it('recusa token inválido', function () {
    User::factory()->create(['email' => 'ana@kairo.test']);

    fromSpa($this)->postJson('/api/v1/auth/reset-password', [
        'token' => 'token-falso',
        'email' => 'ana@kairo.test',
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_reset_token');
});
