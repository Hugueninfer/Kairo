<?php

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function verificationUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

it('verifica o e-mail pelo link assinado e volta para o front', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user))
        ->assertRedirect('http://localhost:5173/login?verificado=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('recusa link com assinatura adulterada', function () {
    $user = User::factory()->unverified()->create();

    $this->getJson(verificationUrl($user).'x')->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('recusa link com hash de outro e-mail', function () {
    $user = User::factory()->unverified()->create();

    $this->getJson(verificationUrl($user, sha1('outro@kairo.test')))
        ->assertForbidden()
        ->assertJsonPath('code', 'invalid_verification_link');
});

it('reenvia o e-mail de verificação para quem ainda não confirmou', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    fromSpa($this)->actingAs($user, 'web')
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertAccepted();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});
