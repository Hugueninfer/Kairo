<?php

namespace App\Services\Auth;

use App\DTOs\Auth\ResetPasswordData;
use App\Exceptions\Auth\InvalidPasswordResetException;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * Envia o link de redefinição. A resposta é sempre a mesma para não revelar
     * quais e-mails têm conta no Kairo.
     */
    public function sendResetLink(string $email): void
    {
        Password::sendResetLink(['email' => mb_strtolower(trim($email))]);
    }

    public function reset(ResetPasswordData $data): void
    {
        $status = Password::reset(
            [
                'email' => mb_strtolower(trim($data->email)),
                'password' => $data->password,
                'token' => $data->token,
            ],
            function (User $user, string $password): void {
                $this->users->updatePassword($user, $password);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new InvalidPasswordResetException;
        }
    }
}
