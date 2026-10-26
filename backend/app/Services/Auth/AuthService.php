<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginData;
use App\DTOs\Auth\RegisterUserData;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\TooManyLoginAttemptsException;
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

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthFactory $auth,
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

    public function login(LoginData $data, string $ip): User
    {
        $throttleKey = 'login:'.mb_strtolower($data->email).'|'.$ip;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw new TooManyLoginAttemptsException(RateLimiter::availableIn($throttleKey));
        }

        $credentials = ['email' => mb_strtolower(trim($data->email)), 'password' => $data->password];

        if (! $this->guard()->attempt($credentials, $data->remember)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            throw new InvalidCredentialsException;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        /** @var User */
        return $this->guard()->user();
    }

    public function logout(): void
    {
        $this->guard()->logout();

        session()->invalidate();
        session()->regenerateToken();
    }

    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard */
        return $this->auth->guard('web');
    }
}
