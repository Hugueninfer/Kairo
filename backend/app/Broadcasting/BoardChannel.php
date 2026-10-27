<?php

namespace App\Broadcasting;

use App\Models\User;
use App\Services\Boards\BoardService;

/**
 * Canal de presença de um board. O que volta daqui é o que os outros veem de quem entrou.
 */
class BoardChannel
{
    public function __construct(private readonly BoardService $boards) {}

    /**
     * @return array{id: int, name: string, can_edit: bool}|false
     */
    public function join(User $user, string $board): array|false
    {
        return $this->boards->presenceMember($user, $board) ?? false;
    }
}
