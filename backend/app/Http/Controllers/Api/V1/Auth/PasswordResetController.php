<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwords) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwords->sendResetLink($request->string('email')->toString());

        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, você vai receber um link para redefinir a senha.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwords->reset($request->toData());

        return response()->json(['message' => 'Senha redefinida. Faça login com a nova senha.']);
    }
}
