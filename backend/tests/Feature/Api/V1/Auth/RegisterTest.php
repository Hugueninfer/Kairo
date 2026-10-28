<?php

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

it('cadastra o usuário, já autentica e envia o e-mail de verificação', function () {
    fromSpa($this)->postJson('/api/v1/auth/register', [
        'name' => 'Ana Souza',
        'email' => 'Ana@Kairo.test',
        'password' => 'SenhaForte123',
        'password_confirmation' => 'SenhaForte123',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'ana@kairo.test')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonMissingPath('data.password');

    $user = User::query()->where('email', 'ana@kairo.test')->sole();

    $this->assertAuthenticatedAs($user, 'web');
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('recusa e-mail já cadastrado e senha fraca com mensagens em português', function () {
    User::factory()->create(['email' => 'ana@kairo.test']);

    fromSpa($this)->postJson('/api/v1/auth/register', [
        'name' => 'Ana',
        'email' => 'ANA@kairo.test',
        'password' => 'fraca',
        'password_confirmation' => 'fraca',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password'])
        ->assertJsonPath('errors.email.0', 'Este e-mail já está em uso.');
});

it('coloca o e-mail de verificação na fila de e-mails', function () {
    expect(new VerifyEmailNotification)
        ->toBeInstanceOf(ShouldQueue::class)
        ->queue->toBe('mail');
});

it('responde em português quando passa do limite de tentativas', function () {
    foreach (range(1, 6) as $attempt) {
        fromSpa($this)->postJson('/api/v1/auth/forgot-password', ['email' => "ana{$attempt}@kairo.test"]);
    }

    fromSpa($this)->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@kairo.test'])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', 'too_many_attempts')
        ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Muitas tentativas. Tente de novo em'));
});
