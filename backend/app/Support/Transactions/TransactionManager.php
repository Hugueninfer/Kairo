<?php

namespace App\Support\Transactions;

/**
 * Permite ao Service agrupar várias chamadas de repository numa transação,
 * sem depender da facade DB (que fica restrita aos repositories).
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
