<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginData;
use App\DTOs\Auth\RegisterUserData;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidTwoFactorCodeException;
use App\Exceptions\Auth\TooManyLoginAttemptsException;
use App\Exceptions\Auth\TwoFactorChallengeExpiredException;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\RateLimiter;

class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 60;

    /** Tempo para digitar o código do aplicativo depois de acertar a senha. */
    private const CHALLENGE_SECONDS = 300;

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthFactory $auth,
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function register(RegisterUserData $data): User
    {
        /** @var User $user */
        $user = $this->users->create($data->toArray());

        // Dispara o e-mail de verificação (em fila).
        event(new Registered($user));

        $this->guard()->login($user);
        session()->regenerate();

        return $user;
    }

    /**
     * Confere e-mail e senha. Devolve null quando a conta tem login em duas etapas:
     * a sessão só é aberta depois do código, em challenge().
     */
    public function login(LoginData $data, string $ip): ?User
    {
        $throttleKey = 'login:'.mb_strtolower($data->email).'|'.$ip;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw new TooManyLoginAttemptsException(RateLimiter::availableIn($throttleKey));
        }

        $credentials = ['email' => mb_strtolower(trim($data->email)), 'password' => $data->password];

        if (! $this->guard()->validate($credentials)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            throw new InvalidCredentialsException;
        }

        RateLimiter::clear($throttleKey);
        $user = $this->users->findByEmail($credentials['email']);

        if ($user === null) {
            throw new InvalidCredentialsException;
        }

        if ($user->hasTwoFactorEnabled()) {
            session()->put('login.pending', [
                'id' => $user->id,
                'remember' => $data->remember,
                'expires_at' => now()->addSeconds(self::CHALLENGE_SECONDS)->getTimestamp(),
            ]);

            return null;
        }

        return $this->open($user, $data->remember);
    }

    /**
     * Segunda etapa: o código do aplicativo (ou de recuperação) de quem acabou de acertar a senha.
     */
    public function challenge(string $code): User
    {
        /** @var array{id: int, remember: bool, expires_at: int}|null $pending */
        $pending = session()->get('login.pending');

        if ($pending === null || $pending['expires_at'] < now()->getTimestamp()) {
            session()->forget('login.pending');

            throw new TwoFactorChallengeExpiredException;
        }

        $throttleKey = 'two-factor:'.$pending['id'];

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw new TooManyLoginAttemptsException(RateLimiter::availableIn($throttleKey));
        }

        $user = $this->users->findOrFail($pending['id']);

        if (! $this->twoFactor->verify($user, $code)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            throw new InvalidTwoFactorCodeException;
        }

        RateLimiter::clear($throttleKey);
        session()->forget('login.pending');

        return $this->open($user, $pending['remember']);
    }

    /**
     * A senha atual já foi conferida na validação. Troca também o token de "lembrar de mim".
     */
    public function changePassword(User $user, string $password): void
    {
        $this->users->updatePassword($user, $password);
        session()->regenerate();
    }

    public function logout(): void
    {
        $this->guard()->logout();

        session()->invalidate();
        session()->regenerateToken();
    }

    private function open(User $user, bool $remember): User
    {
        $this->guard()->login($user, $remember);
        session()->regenerate();

        return $user;
    }

    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard */
        return $this->auth->guard('web');
    }
}
