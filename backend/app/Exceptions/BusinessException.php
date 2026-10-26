<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Erro de regra de negócio lançado pelos Services.
 *
 * Vira uma resposta JSON padronizada: { "message": "...", "code": "..." }.
 * Cada caso concreto (ex.: InvalidStatusTransition) estende esta classe.
 */
abstract class BusinessException extends Exception
{
    protected int $status = 422;

    /**
     * Código estável que o front usa para tratar o erro sem depender do texto.
     */
    abstract public function errorCode(): string;

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode(),
        ], $this->status);
    }
}
