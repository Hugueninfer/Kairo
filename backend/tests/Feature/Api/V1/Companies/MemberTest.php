<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lista os membros da empresa ativa', function () {
    [$owner, $company] = ownerWithCompany();
    memberOf($company);

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/members')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('o dono remove um membro e a empresa deixa de ser a ativa dele', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/members/{$member->id}")
        ->assertNoContent();

    expect($company->members()->whereKey($member->id)->exists())->toBeFalse()
        ->and($member->fresh()->current_company_id)->toBeNull();
});

it('o dono não pode ser removido', function () {
    [$owner] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/members/{$owner->id}")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'cannot_remove_owner');
});
