<?php

namespace App\Http\Controllers\Api\V1\Boards;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBoardResource;
use App\Services\Boards\BoardService;

/**
 * Board aberto pelo cliente com o link de leitura, sem login.
 */
class PublicBoardController extends Controller
{
    public function __construct(private readonly BoardService $boards) {}

    public function show(string $token): PublicBoardResource
    {
        return PublicBoardResource::make($this->boards->openShared($token));
    }
}
