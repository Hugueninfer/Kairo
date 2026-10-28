<?php

use App\Enums\InvoiceStatus;
use App\Enums\SystemRole;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Invoice;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registra criação e alteração com quem fez, os campos mudados e textos em português', function () {
    [$owner, $company] = ownerWithCompany();

    $id = fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/clients', ['name' => 'Padaria Pão Quente', 'email' => 'contato@padaria.test'])
        ->assertCreated()
        ->json('data.id');
    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/clients/{$id}", ['name' => 'Padaria Pão Quente Ltda', 'email' => 'contato@padaria.test'])
        ->assertOk();

    $history = fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/audit')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    expect($history[0])->toMatchArray([
        'subject' => ['value' => 'client', 'label' => 'Clientes'],
        'causer' => ['id' => $owner->id, 'name' => $owner->name],
        'summary' => 'alterou o cliente Padaria Pão Quente Ltda',
        'changes' => [['field' => 'name', 'label' => 'Nome', 'old' => 'Padaria Pão Quente', 'new' => 'Padaria Pão Quente Ltda']],
    ])->and($history[1]['summary'])->toBe('criou o cliente Padaria Pão Quente')
        // Na criação, os campos vazios (telefone, documento) ficam de fora.
        ->and(array_column($history[1]['changes'], 'field'))->toBe(['name', 'email']);

    expect(Activity::query()->withoutGlobalScopes()->pluck('company_id')->unique()->all())->toBe([$company->id]);
});

it('grava situação com o texto em português e itens alterados pela fila na empresa certa', function () {
    [$owner, $company] = ownerWithCompany();
    $invoice = Invoice::factory()->open()->create(['company_id' => $company->id]);

    // Como num job da fila: sem empresa ativa e sem usuário.
    app(TenantContext::class)->forget();
    Invoice::query()->withoutGlobalScopes()->findOrFail($invoice->id)->update(['status' => InvoiceStatus::Overdue]);

    $row = fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/audit?subject=invoice')
        ->assertOk()
        ->json('data.0');

    expect($row['causer'])->toBeNull()
        ->and($row['summary'])->toBe("alterou a fatura {$invoice->number}")
        ->and($row['changes'][0])->toMatchArray(['label' => 'Situação', 'old' => 'Em aberto', 'new' => 'Vencida']);
});

it('registra mudanças de papel e de equipe que não são cadastros simples', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);
    $finance = roleOf($company, SystemRole::Finance);

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/members/{$member->id}/role", ['role_id' => $finance->id])->assertSuccessful();
    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/members/{$member->id}")->assertNoContent();

    $rows = fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/audit?subject=member')->assertOk()->json('data');

    expect(array_column($rows, 'summary'))->toBe(["removeu {$member->name} da equipe", "mudou o papel de {$member->name}"])
        ->and($rows[1]['changes'][0])->toMatchArray(['label' => 'Papel', 'old' => 'Membro', 'new' => 'Financeiro']);
});

it('filtra por pessoa e por período, e só mostra o histórico da própria empresa', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company, SystemRole::Admin);
    [$stranger, $other] = ownerWithCompany();

    $this->actingAs($owner, 'web');
    Client::factory()->create(['company_id' => $company->id, 'name' => 'Do dono']);
    $this->actingAs($member, 'web');
    Client::factory()->create(['company_id' => $company->id, 'name' => 'Da admin']);
    $this->actingAs($stranger, 'web');
    Client::factory()->create(['company_id' => $other->id, 'name' => 'De outra empresa']);
    $this->app['auth']->forgetGuards();

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/audit?user={$member->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.summary', 'criou o cliente Da admin');

    $today = now($company->timezone)->toDateString();
    $tomorrow = now($company->timezone)->addDay()->toDateString();
    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/audit?subject=client&from={$today}&to={$today}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/audit?from={$tomorrow}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('só dono e administrador veem o histórico', function () {
    [, $company] = ownerWithCompany();

    fromSpa($this)->actingAs(memberOf($company, SystemRole::Finance), 'web')->getJson('/api/v1/company/audit')->assertForbidden();
    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs(memberOf($company, SystemRole::Admin), 'web')->getJson('/api/v1/company/audit')->assertOk();
});

it('mostra valores em dinheiro na moeda da empresa', function () {
    [$owner] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/projects', ['name' => 'Site', 'hourly_rate' => 150, 'budget' => 1500])
        ->assertCreated();

    $changes = collect(fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/audit')->json('data.0.changes'))->keyBy('field');

    expect($changes['hourly_rate']['new'])->toBe("R\$\u{a0}150,00")
        ->and($changes['budget']['new'])->toBe("R\$\u{a0}1.500,00")
        ->and($changes->has('due_on'))->toBeFalse();
});
