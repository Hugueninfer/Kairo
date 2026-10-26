<?php

namespace App\Support\Transactions;

use Illuminate\Database\ConnectionInterface;

class DatabaseTransactionManager implements TransactionManager
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function run(callable $callback): mixed
    {
        return $this->connection->transaction(fn () => $callback());
    }
}
