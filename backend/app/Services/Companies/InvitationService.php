<?php

namespace App\Services\Companies;

use App\Enums\AuditSubject;
use App\Exceptions\Companies\AlreadyMemberException;
use App\Exceptions\Companies\InvalidInvitationException;
use App\Exceptions\Companies\InvitationEmailMismatchException;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\Companies\InvitationNotification;
use App\Repositories\Contracts\CompanyRepository;
use App\Repositories\Contracts\InvitationRepository;
use App\Repositories\Contracts\UserRepository;
use App\Services\Audit\AuditService;
use App\Support\Transactions\TransactionManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly CompanyRepository $companies,
        private readonly UserRepository $users,
        private readonly RoleService $roles,
        private readonly TransactionManager $transaction,
        private readonly AuditService $audit,
    ) {}

    /**
     * @return Collection<int, Invitation>
     */
    public function pending(): Collection
    {
        return $this->invitations->pendingForCompany();
    }

    /**
     * Convida por e-mail. Um convite anterior pendente para o mesmo e-mail é cancelado,
     * para existir só um link válido por pessoa. Sem papel escolhido, a pessoa entra como membro.
     */
    public function invite(Company $company, User $inviter, string $email, ?int $roleId = null): Invitation
    {
        $role = $roleId === null ? null : $this->roles->find($company, $roleId);

        if ($role !== null) {
            $this->roles->ensureAssignable($company, $role);
        }

        $existing = $this->users->findByEmail($email);

        if ($existing !== null && $this->companies->isMember($company, $existing)) {
            throw new AlreadyMemberException;
        }

        $this->invitations->revokePendingForEmail($email);

        $token = Str::random(64);
        $invitation = $this->invitations->createPending($company, $inviter, $email, $this->hash($token), $role);

        Notification::route('mail', $email)->notify(new InvitationNotification(
            companyName: $company->name,
            inviterName: $inviter->name,
            token: $token,
        ));

        return $invitation;
    }

    public function revoke(Invitation $invitation): void
    {
        $this->invitations->revoke($invitation);
    }

    public function findByToken(string $token): Invitation
    {
        return $this->invitations->findPendingByTokenHash($this->hash($token))
            ?? throw new InvalidInvitationException;
    }

    /**
     * Aceita o convite com a conta logada. O e-mail precisa ser o mesmo do convite.
     */
    public function accept(string $token, User $user): Company
    {
        $invitation = $this->findByToken($token);

        if (mb_strtolower($invitation->email) !== mb_strtolower($user->email)) {
            throw new InvitationEmailMismatchException;
        }

        $company = $invitation->company;

        $this->transaction->run(function () use ($invitation, $company, $user): void {
            $this->companies->addMember($company, $user);
            $this->roles->assignOnJoin($user, $company, $invitation->role);
            $this->invitations->markAccepted($invitation);
            $this->users->setCurrentCompany($user, $company);
        });

        $this->audit->record($company->id, AuditSubject::Member, 'joined', $user->name, new: ['role' => $this->roles->roleOf($user, $company)?->name]);

        return $company;
    }

    public function expireOverdue(): int
    {
        return $this->invitations->expireOverdue();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
