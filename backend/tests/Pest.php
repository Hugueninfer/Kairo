<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * Simula uma requisição vinda do front (SPA), para o Sanctum usar sessão e cookies.
 */
function fromSpa(TestCase $test): TestCase
{
    return $test->withHeader('Referer', 'http://localhost:5173');
}
