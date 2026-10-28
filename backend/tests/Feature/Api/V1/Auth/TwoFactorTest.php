<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

/**
 * Conta com login em duas etapas já ligado e um código de recuperação conhecido.
 */
function userWithTwoFactor(): User
{
    $user = User::factory()->create(['email' => 'ana@kairo.test', 'password' => 'SenhaForte123']);
    $user->forceFill([
        'two_factor_secret' => app(Google2FA::class)->generateSecretKey(32),
        'two_factor_recovery_codes' => [hash('sha256', 'abcde-fghij')],
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

function otpOf(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp((string) $user->fresh()?->two_factor_secret);
}

it('liga o login em duas etapas com a senha e o primeiro código do aplicativo', function () {
    $user = User::factory()->create(['password' => 'SenhaForte123']);

    fromSpa($this)->actingAs($user, 'web')->postJson('/api/v1/auth/two-factor', ['password' => 'errada'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_password');

    $setup = fromSpa($this)->actingAs($user, 'web')->postJson('/api/v1/auth/two-factor', ['password' => 'SenhaForte123'])
        ->assertOk()
        ->json('data');

    expect($setup['otpauth_url'])->toStartWith('otpauth://totp/')
        ->and($setup['otpauth_url'])->toContain($setup['secret'])
        // O segredo fica criptografado no banco.
        ->and(DB::table('users')->where('id', $user->id)->value('two_factor_secret'))->not->toContain($setup['secret']);

    fromSpa($this)->actingAs($user, 'web')->postJson('/api/v1/auth/two-factor/confirm', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_two_factor_code');

    $codes = fromSpa($this)->actingAs($user, 'web')->postJson('/api/v1/auth/two-factor/confirm', ['code' => otpOf($user)])
        ->assertOk()
        ->json('data.recovery_codes');

    expect($codes)->toHaveCount(8);
    fromSpa($this)->actingAs($user->fresh(), 'web')->getJson('/api/v1/auth/me')->assertJsonPath('data.two_factor_enabled', true);
});

it('pede o código depois da senha e só abre a sessão com ele', function () {
    $user = userWithTwoFactor();

    fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ana@kairo.test', 'password' => 'SenhaForte123'])
        ->assertOk()
        ->assertExactJson(['two_factor' => true]);
    $this->assertGuest('web');

    fromSpa($this)->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_two_factor_code');
    $this->assertGuest('web');

    $code = otpOf($user);
    fromSpa($this)->postJson('/api/v1/auth/two-factor-challenge', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
    $this->assertAuthenticatedAs($user, 'web');

    // O mesmo código não vale de novo.
    $this->app['auth']->forgetGuards();
    $this->post('/api/v1/auth/logout');
    fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ana@kairo.test', 'password' => 'SenhaForte123']);
    fromSpa($this)->postJson('/api/v1/auth/two-factor-challenge', ['code' => $code])
        ->assertJsonPath('code', 'invalid_two_factor_code');
});

it('aceita cada código de recuperação uma vez só', function () {
    $user = userWithTwoFactor();

    fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ana@kairo.test', 'password' => 'SenhaForte123']);
    fromSpa($this)->postJson('/api/v1/auth/two-factor-challenge', ['code' => 'ABCDE-FGHIJ'])->assertOk();

    expect($user->fresh()?->two_factor_recovery_codes)->toBe([]);
});

it('expira a verificação depois de 5 minutos e não aceita código sem a senha antes', function () {
    userWithTwoFactor();

    fromSpa($this)->postJson('/api/v1/auth/two-factor-challenge', ['code' => '123456'])
        ->assertJsonPath('code', 'two_factor_expired');

    fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ana@kairo.test', 'password' => 'SenhaForte123']);
    $this->travel(6)->minutes();

    fromSpa($this)->postJson('/api/v1/auth/two-factor-challenge', ['code' => 'abcde-fghij'])
        ->assertJsonPath('code', 'two_factor_expired');
    $this->assertGuest('web');
});

it('desliga com a senha e gera códigos de recuperação novos', function () {
    $user = userWithTwoFactor();

    $codes = fromSpa($this)->actingAs($user, 'web')->postJson('/api/v1/auth/two-factor/recovery-codes', ['password' => 'SenhaForte123'])
        ->assertOk()
        ->json('data.recovery_codes');
    expect($codes)->toHaveCount(8)->and($user->fresh()?->two_factor_recovery_codes)->not->toContain(hash('sha256', 'abcde-fghij'));

    fromSpa($this)->actingAs($user, 'web')->deleteJson('/api/v1/auth/two-factor', ['password' => 'errada'])->assertUnprocessable();
    fromSpa($this)->actingAs($user, 'web')->deleteJson('/api/v1/auth/two-factor', ['password' => 'SenhaForte123'])->assertNoContent();

    expect($user->fresh()?->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->fresh()?->two_factor_secret)->toBeNull();
});

it('troca a senha conferindo a atual', function () {
    $user = User::factory()->create(['email' => 'ana@kairo.test', 'password' => 'SenhaForte123']);

    fromSpa($this)->actingAs($user, 'web')->putJson('/api/v1/auth/password', [
        'current_password' => 'errada', 'password' => 'NovaSenha456', 'password_confirmation' => 'NovaSenha456',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    fromSpa($this)->actingAs($user, 'web')->putJson('/api/v1/auth/password', [
        'current_password' => 'SenhaForte123', 'password' => 'NovaSenha456', 'password_confirmation' => 'NovaSenha456',
    ])->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->post('/api/v1/auth/logout');
    fromSpa($this)->postJson('/api/v1/auth/login', ['email' => 'ana@kairo.test', 'password' => 'NovaSenha456'])->assertOk();
});
