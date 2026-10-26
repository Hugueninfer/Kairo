<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidVerificationLinkException;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Auth\Events\Verified;

class EmailVerificationService
{
    public function __construct(private readonly UserRepository $users) {}

    public function verify(int $userId, string $hash): User
    {
        $user = $this->users->findOrFail($userId);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new InvalidVerificationLinkException;
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return $user;
    }

    public function resend(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }
}
