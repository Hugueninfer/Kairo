<?php

use Tests\Fixtures\ExampleData;
use Tests\Fixtures\ExampleStatus;

it('cria o DTO a partir de um array', function () {
    $data = ExampleData::from(['name' => 'Kairo', 'status' => ExampleStatus::Sent]);

    expect($data->name)->toBe('Kairo')
        ->and($data->status)->toBe(ExampleStatus::Sent);
});

it('converte o DTO em array com enums como valores', function () {
    $data = new ExampleData('Kairo', ExampleStatus::Draft);

    expect($data->toArray())->toBe(['name' => 'Kairo', 'status' => 'draft']);
});
