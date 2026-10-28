<?php

namespace App\Http\Controllers\Api\V1\Boards;

use App\Http\Controllers\Controller;
use App\Http\Resources\BoardResource;
use App\Models\Board;
use App\Services\Boards\BoardService;
use Dedoc\Scramble\Attributes\Group;

/**
 * Liga e desliga o link de leitura do board para o cliente.
 */
#[Group('Boards', weight: 13)]
class BoardShareController extends Controller
{
    public function __construct(private readonly BoardService $boards) {}

    public function store(Board $board): BoardResource
    {
        return BoardResource::make($this->boards->share($board));
    }

    public function destroy(Board $board): BoardResource
    {
        return BoardResource::make($this->boards->unshare($board));
    }
}
