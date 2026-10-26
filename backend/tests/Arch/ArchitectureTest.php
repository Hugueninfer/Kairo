<?php

// Regras de arquitetura do Kairo. Elas falham o CI se alguém furar as camadas.

arch('controllers não acessam o banco diretamente')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Database\Query\Builder',
    ]);

arch('código de produção não usa funções de debug')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch()->preset()->security()->ignoring('md5');
