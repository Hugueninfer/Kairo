<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Companies\CompanyController;
use App\Http\Controllers\Api\V1\Companies\InvitationController;
use App\Http\Controllers\Api\V1\Companies\MemberController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:auth')->name('password.forgot');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth')->name('password.reset');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:auth')
                ->name('verification.send');
        });
    });

    // Convite: a prévia é pública (quem recebeu pode nem ter conta); aceitar exige login.
    Route::get('invitations/{token}', [InvitationController::class, 'show'])
        ->middleware('throttle:auth')
        ->name('invitations.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])
            ->middleware('throttle:auth')
            ->name('invitations.accept');

        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::post('companies/{company}/switch', [CompanyController::class, 'switch'])->name('companies.switch');

        // Tudo daqui para baixo roda dentro da empresa ativa (TenantContext).
        Route::middleware('tenant')->prefix('company')->name('company.')->group(function (): void {
            Route::get('/', [CompanyController::class, 'current'])->name('show');
            Route::patch('/', [CompanyController::class, 'update'])->name('update');

            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

            Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
            Route::post('invitations', [InvitationController::class, 'store'])
                ->middleware('throttle:auth')
                ->name('invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        });
    });
});

// Nome padrão do Laravel, usado pela notificação de verificação para montar o link assinado.
Route::get('v1/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:auth'])
    ->whereNumber('id')
    ->name('verification.verify');
