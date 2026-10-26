<?php

// Regras de arquitetura do Kairo. Elas falham o CI se alguém furar as camadas.

arch('controllers não acessam o banco diretamente')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Database\Query\Builder',
        'App\Repositories',
    ]);

arch('services não acessam o banco diretamente, só via repositories')
    ->expect('App\Services')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Database\Query\Builder',
        'App\Repositories\Eloquent',
    ]);

arch('contratos de repository são interfaces')
    ->expect('App\Repositories\Contracts')
    ->toBeInterfaces();

arch('repositories Eloquent estendem a base do projeto')
    ->expect('App\Repositories\Eloquent')
    ->classes()
    ->toExtend('Prettus\Repository\Eloquent\BaseRepository')
    ->toHaveSuffix('Eloquent')
    ->ignoring('App\Repositories\Eloquent\BaseRepository');

arch('DTOs são imutáveis')
    ->expect('App\DTOs')
    ->toBeReadonly();

arch('código de produção não usa funções de debug')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch()->preset()->security()->ignoring('md5');
