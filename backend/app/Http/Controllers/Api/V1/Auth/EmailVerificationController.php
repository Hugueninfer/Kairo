<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationService $verification) {}

    /**
     * Link do e-mail (assinado). Depois de verificar, leva o usuário de volta ao front.
     */
    public function verify(int $id, string $hash): RedirectResponse
    {
        $this->verification->verify($id, $hash);

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/login?verificado=1');
    }

    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->verification->resend($user);

        return response()->json(['message' => 'Enviamos um novo link de verificação para o seu e-mail.'], 202);
    }
}
