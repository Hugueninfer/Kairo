<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Liga, confirma e desliga o login em duas etapas de quem está logado.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function store(ConfirmPasswordRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->twoFactor->start($this->user($request), $request->string('password')->toString())]);
    }

    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        return response()->json(['data' => ['recovery_codes' => $this->twoFactor->confirm($this->user($request), $request->string('code')->toString())]]);
    }

    public function recoveryCodes(ConfirmPasswordRequest $request): JsonResponse
    {
        $codes = $this->twoFactor->regenerateRecoveryCodes($this->user($request), $request->string('password')->toString());

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }

    public function destroy(ConfirmPasswordRequest $request): Response
    {
        $this->twoFactor->disable($this->user($request), $request->string('password')->toString());

        return response()->noContent();
    }

    private function user(ConfirmPasswordRequest|TwoFactorCodeRequest $request): User
    {
        /** @var User */
        return $request->user();
    }
}
