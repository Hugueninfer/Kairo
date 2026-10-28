<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Autenticação', weight: 1)]
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->toData());

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    /**
     * Com login em duas etapas, responde só { two_factor: true } e espera o código em twoFactorChallenge.
     */
    public function login(LoginRequest $request): JsonResponse|UserResource
    {
        $user = $this->auth->login($request->toData(), (string) $request->ip());

        return $user === null ? response()->json(['two_factor' => true]) : UserResource::make($user);
    }

    public function twoFactorChallenge(TwoFactorCodeRequest $request): UserResource
    {
        return UserResource::make($this->auth->challenge($request->string('code')->toString()));
    }

    public function changePassword(ChangePasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->auth->changePassword($user, $request->string('password')->toString());

        return response()->noContent();
    }

    public function logout(): Response
    {
        $this->auth->logout();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
