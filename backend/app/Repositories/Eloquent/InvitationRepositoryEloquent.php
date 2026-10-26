<?php

namespace App\Repositories\Eloquent;

use App\Enums\InvitationStatus;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\InvitationRepository;
use Illuminate\Database\Eloquent\Collection;

class InvitationRepositoryEloquent extends BaseRepository implements InvitationRepository
{
    private const EXPIRES_IN_DAYS = 7;

    public function model(): string
    {
        return Invitation::class;
    }

    public function createPending(Company $company, User $inviter, string $email, string $tokenHash, ?Role $role = null): Invitation
    {
        $invitation = new Invitation([
            'email' => $email,
            'token_hash' => $tokenHash,
            'invited_by' => $inviter->id,
            'role_id' => $role?->id,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
        ]);
        $invitation->company()->associate($company);
        $invitation->save();

        return $invitation->load(['inviter', 'role']);
    }

    public function findPendingByTokenHash(string $tokenHash): ?Invitation
    {
        /** @var Invitation|null */
        return Invitation::query()
            ->withoutGlobalScope('company')
            ->with(['company', 'inviter', 'role'])
            ->where('token_hash', $tokenHash)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function pendingForCompany(): Collection
    {
        $this->applyCriteria();
        $this->applyScope();

        /** @var Collection<int, Invitation> $invitations */
        $invitations = $this->model
            ->with(['inviter', 'role'])
            ->where('status', InvitationStatus::Pending)
            ->latest()
            ->get();

        $this->resetModel();

        return $invitations;
    }

    public function revokePendingForEmail(string $email): void
    {
        $this->applyCriteria();

        $this->model
            ->where('email', $email)
            ->where('status', InvitationStatus::Pending)
            ->update(['status' => InvitationStatus::Revoked]);

        $this->resetModel();
    }

    public function markAccepted(Invitation $invitation): void
    {
        $invitation->forceFill(['status' => InvitationStatus::Accepted, 'accepted_at' => now()])->save();
    }

    public function revoke(Invitation $invitation): void
    {
        $invitation->forceFill(['status' => InvitationStatus::Revoked])->save();
    }

    public function expireOverdue(): int
    {
        return Invitation::query()
            ->withoutGlobalScope('company')
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => InvitationStatus::Expired]);
    }
}
