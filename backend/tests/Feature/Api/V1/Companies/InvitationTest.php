<?php

use App\Enums\InvitationStatus;
use App\Jobs\ExpireInvitations;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\Companies\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('convida por e-mail com link para o front e guarda só o hash do token', function () {
    Notification::fake();
    [$owner, $company] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')
        ->postJson('/api/v1/company/invitations', ['email' => 'Bruno@Kairo.test'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'bruno@kairo.test')
        ->assertJsonPath('data.status.value', 'pending')
        ->assertJsonMissingPath('data.token_hash');

    $token = null;
    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$token) {
        $token = $notification->token;

        return $notifiable->routes['mail'] === 'bruno@kairo.test'
            && str_starts_with($notification->toMail($notifiable)->actionUrl, 'http://localhost:5173/convite/');
    });

    $invitation = Invitation::query()->sole();
    expect($invitation->company_id)->toBe($company->id)
        ->and($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->token_hash)->not->toBe($token);
});

it('não convida quem já é membro', function () {
    Notification::fake();
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);

    fromSpa($this)->actingAs($owner, 'web')
        ->postJson('/api/v1/company/invitations', ['email' => $member->email])
        ->assertStatus(409)
        ->assertJsonPath('code', 'already_member');
});

it('membro comum não pode convidar', function () {
    [, $company] = ownerWithCompany();
    $member = memberOf($company);

    fromSpa($this)->actingAs($member, 'web')
        ->postJson('/api/v1/company/invitations', ['email' => 'x@kairo.test'])
        ->assertForbidden();
});

it('mostra a prévia pública do convite sem dados internos', function () {
    [, $company] = ownerWithCompany();
    Invitation::factory()->for($company)->withToken('token-valido')->create(['email' => 'bruno@kairo.test']);

    $this->getJson('/api/v1/invitations/token-valido')
        ->assertOk()
        ->assertExactJsonStructure(['data' => ['company_name', 'invited_by', 'email', 'expires_at']])
        ->assertJsonPath('data.company_name', $company->name);
});

it('aceita o convite com a conta do e-mail convidado', function () {
    [, $company] = ownerWithCompany();
    $invitation = Invitation::factory()->for($company)->withToken('token-valido')->create(['email' => 'bruno@kairo.test']);
    $bruno = User::factory()->create(['email' => 'bruno@kairo.test']);

    fromSpa($this)->actingAs($bruno, 'web')
        ->postJson('/api/v1/invitations/token-valido/accept')
        ->assertOk()
        ->assertJsonPath('data.id', $company->ulid)
        ->assertJsonPath('data.is_current', true);

    expect($company->members()->whereKey($bruno->id)->exists())->toBeTrue()
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Accepted);

    // O mesmo link não funciona duas vezes.
    fromSpa($this)->actingAs($bruno, 'web')
        ->postJson('/api/v1/invitations/token-valido/accept')
        ->assertNotFound()
        ->assertJsonPath('code', 'invalid_invitation');
});

it('recusa aceitar com uma conta de outro e-mail', function () {
    [, $company] = ownerWithCompany();
    Invitation::factory()->for($company)->withToken('token-valido')->create(['email' => 'bruno@kairo.test']);
    $intruso = User::factory()->create();

    fromSpa($this)->actingAs($intruso, 'web')
        ->postJson('/api/v1/invitations/token-valido/accept')
        ->assertForbidden()
        ->assertJsonPath('code', 'invitation_email_mismatch');

    expect($company->members()->whereKey($intruso->id)->exists())->toBeFalse();
});

it('não aceita convite expirado', function () {
    [, $company] = ownerWithCompany();
    Invitation::factory()->for($company)->withToken('token-velho')->expired()->create();

    $this->getJson('/api/v1/invitations/token-velho')->assertNotFound();
});

it('o job diário marca convites vencidos como expirados', function () {
    [, $company] = ownerWithCompany();
    $vencido = Invitation::factory()->for($company)->expired()->create();
    $valido = Invitation::factory()->for($company)->create();

    dispatch_sync(new ExpireInvitations);

    expect($vencido->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($valido->fresh()->status)->toBe(InvitationStatus::Pending);
});

it('um novo convite para o mesmo e-mail cancela o anterior', function () {
    Notification::fake();
    [$owner, $company] = ownerWithCompany();
    $antigo = Invitation::factory()->for($company)->create(['email' => 'bruno@kairo.test']);

    fromSpa($this)->actingAs($owner, 'web')
        ->postJson('/api/v1/company/invitations', ['email' => 'bruno@kairo.test'])
        ->assertCreated();

    expect($antigo->fresh()->status)->toBe(InvitationStatus::Revoked);
});
