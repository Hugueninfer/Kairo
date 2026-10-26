<?php

use Tests\Fixtures\ExampleStatus;

it('lista os valores do enum', function () {
    expect(ExampleStatus::values())->toBe(['draft', 'sent']);
});

it('monta as opções com rótulo para o front', function () {
    expect(ExampleStatus::options())->toBe([
        ['value' => 'draft', 'label' => 'Rascunho'],
        ['value' => 'sent', 'label' => 'Enviado'],
    ]);
});
