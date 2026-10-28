<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidPasswordException;
use App\Exceptions\Auth\InvalidTwoFactorCodeException;
use App\Exceptions\Auth\TwoFactorAlreadyEnabledException;
use App\Exceptions\Auth\TwoFactorNotStartedException;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Login em duas etapas com aplicativo autenticador (TOTP: Google Authenticator, Authy, 1Password...).
 *
 * Fluxo: start() gera o segredo e o QR code, confirm() liga com o primeiro código e devolve os
 * códigos de recuperação (mostrados uma vez só; guardamos apenas o hash de cada um).
 */
class TwoFactorService
{
    private const RECOVERY_CODES = 8;

    public function __construct(
        private readonly UserRepository $users,
        private readonly Google2FA $google2fa,
        private readonly Hasher $hasher,
    ) {}

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function start(User $user, string $password): array
    {
        $this->ensurePassword($user, $password);

        if ($user->hasTwoFactorEnabled()) {
            throw new TwoFactorAlreadyEnabledException;
        }

        $secret = $this->google2fa->generateSecretKey(32);
        $this->users->setTwoFactorSecret($user, $secret);

        return [
            'secret' => $secret,
            'otpauth_url' => $this->google2fa->getQRCodeUrl((string) config('app.name'), $user->email, $secret),
        ];
    }

    /**
     * @return list<string> códigos de recuperação em texto, para a pessoa guardar
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->two_factor_secret === null) {
            throw new TwoFactorNotStartedException;
        }

        if ($user->hasTwoFactorEnabled()) {
            throw new TwoFactorAlreadyEnabledException;
        }

        if (! $this->verifyCode($user, $code)) {
            throw new InvalidTwoFactorCodeException;
        }

        $codes = $this->newRecoveryCodes();
        $this->users->confirmTwoFactor($user, array_map($this->hashCode(...), $codes));

        return $codes;
    }

    public function disable(User $user, string $password): void
    {
        $this->ensurePassword($user, $password);
        $this->users->setTwoFactorSecret($user, null);
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user, string $password): array
    {
        $this->ensurePassword($user, $password);

        if (! $user->hasTwoFactorEnabled()) {
            throw new TwoFactorNotStartedException;
        }

        $codes = $this->newRecoveryCodes();
        $this->users->setRecoveryCodes($user, array_map($this->hashCode(...), $codes));

        return $codes;
    }

    /**
     * Confere o código do aplicativo (6 dígitos) ou um código de recuperação, que deixa de valer.
     */
    public function verify(User $user, string $code): bool
    {
        $code = trim($code);

        if (preg_match('/^\d{6}$/', str_replace(' ', '', $code)) === 1) {
            return $this->verifyCode($user, str_replace(' ', '', $code));
        }

        $hashes = $user->two_factor_recovery_codes ?? [];
        $hash = $this->hashCode($code);

        if (! in_array($hash, $hashes, true)) {
            return false;
        }

        $this->users->setRecoveryCodes($user, array_values(array_diff($hashes, [$hash])));

        return true;
    }

    private function verifyCode(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if ($secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        // Com a janela do último código, o mesmo código não pode ser reaproveitado.
        $window = $this->google2fa->verifyKeyNewer($secret, $code, $user->two_factor_last_window ?? 0);

        if (! is_int($window)) {
            return false;
        }

        $this->users->setTwoFactorWindow($user, $window);

        return true;
    }

    private function ensurePassword(User $user, string $password): void
    {
        if (! $this->hasher->check($password, $user->password)) {
            throw new InvalidPasswordException;
        }
    }

    /**
     * @return list<string>
     */
    private function newRecoveryCodes(): array
    {
        return array_map(
            fn () => Str::lower(Str::random(5).'-'.Str::random(5)),
            range(1, self::RECOVERY_CODES),
        );
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', mb_strtolower(trim($code)));
    }
}
