<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Companies\CompanyController;
use App\Http\Controllers\Api\V1\Companies\InvitationController;
use App\Http\Controllers\Api\V1\Companies\MemberController;
use App\Http\Controllers\Api\V1\Companies\RoleController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OptionsController;
use App\Http\Controllers\Api\V1\Projects\ClientController;
use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\TaskController;
use App\Http\Controllers\Api\V1\Quotes\ContractController;
use App\Http\Controllers\Api\V1\Quotes\PublicQuoteController;
use App\Http\Controllers\Api\V1\Quotes\QuoteController;
use App\Http\Controllers\Api\V1\Timesheet\TimeEntryController;
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

    // Orçamento aberto pelo cliente pelo link do e-mail: sem login, só com a assinatura do link.
    // GET e POST usam a mesma URL assinada, então o link do e-mail serve para ver e responder.
    Route::prefix('public/quotes/{quote}')->name('public.quotes.')->middleware(['signed:relative', 'throttle:public'])
        ->whereUlid('quote')
        ->group(function (): void {
            Route::get('/', [PublicQuoteController::class, 'show'])->name('show');
            Route::post('/', [PublicQuoteController::class, 'decide'])->name('decide');
        });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])
            ->middleware('throttle:auth')
            ->name('invitations.accept');

        Route::get('options', OptionsController::class)->name('options');

        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::post('companies/{company}/switch', [CompanyController::class, 'switch'])->name('companies.switch');

        // Tudo daqui para baixo roda dentro da empresa ativa (TenantContext).
        // Cada rota exige uma permissão do papel do usuário; o dono passa em todas.
        Route::middleware('tenant')->prefix('company')->name('company.')->group(function (): void {
            Route::get('/', [CompanyController::class, 'current'])->name('show');
            Route::patch('/', [CompanyController::class, 'update'])
                ->can(Permission::CompanyUpdate->value)
                ->name('update');

            Route::get('members', [MemberController::class, 'index'])
                ->can(Permission::MembersView->value)
                ->name('members.index');
            Route::put('members/{member}/role', [MemberController::class, 'updateRole'])
                ->can(Permission::RolesManage->value)
                ->name('members.role');
            Route::delete('members/{member}', [MemberController::class, 'destroy'])
                ->can(Permission::MembersRemove->value)
                ->name('members.destroy');

            Route::get('invitations', [InvitationController::class, 'index'])
                ->can(Permission::MembersInvite->value)
                ->name('invitations.index');
            Route::post('invitations', [InvitationController::class, 'store'])
                ->middleware('throttle:auth')
                ->can(Permission::MembersInvite->value)
                ->name('invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])
                ->can(Permission::MembersInvite->value)
                ->name('invitations.destroy');

            Route::get('permissions', [RoleController::class, 'permissions'])
                ->can(Permission::RolesManage->value)
                ->name('permissions.index');
            Route::get('roles', [RoleController::class, 'index'])
                ->can(Permission::MembersView->value)
                ->name('roles.index');
            Route::post('roles', [RoleController::class, 'store'])
                ->can(Permission::RolesManage->value)
                ->name('roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])
                ->can(Permission::RolesManage->value)
                ->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])
                ->can(Permission::RolesManage->value)
                ->name('roles.destroy');

            Route::get('clients', [ClientController::class, 'index'])->can(Permission::ClientsView->value)->name('clients.index');
            Route::get('clients/{client}', [ClientController::class, 'show'])->can(Permission::ClientsView->value)->name('clients.show');
            Route::post('clients', [ClientController::class, 'store'])->can(Permission::ClientsManage->value)->name('clients.store');
            Route::put('clients/{client}', [ClientController::class, 'update'])->can(Permission::ClientsManage->value)->name('clients.update');
            Route::delete('clients/{client}', [ClientController::class, 'destroy'])->can(Permission::ClientsManage->value)->name('clients.destroy');

            Route::get('projects', [ProjectController::class, 'index'])->can(Permission::ProjectsView->value)->name('projects.index');
            Route::get('projects/{project}', [ProjectController::class, 'show'])->can(Permission::ProjectsView->value)->name('projects.show');
            Route::post('projects', [ProjectController::class, 'store'])->can(Permission::ProjectsManage->value)->name('projects.store');
            Route::put('projects/{project}', [ProjectController::class, 'update'])->can(Permission::ProjectsManage->value)->name('projects.update');
            Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->can(Permission::ProjectsManage->value)->name('projects.destroy');

            // Horas: cada um lança as próprias. Ver a equipe toda e aprovar exigem permissão.
            Route::prefix('time-entries')->name('time-entries.')->middleware('can:'.Permission::ProjectsView->value)->group(function (): void {
                Route::get('/', [TimeEntryController::class, 'index'])->name('index');
                Route::get('week', [TimeEntryController::class, 'week'])->name('week');
                Route::get('export', [TimeEntryController::class, 'export'])->name('export');
                Route::post('/', [TimeEntryController::class, 'store'])->name('store');
                Route::get('timer', [TimeEntryController::class, 'timer'])->name('timer');
                Route::post('timer', [TimeEntryController::class, 'start'])->name('timer.start');
                Route::delete('timer', [TimeEntryController::class, 'stop'])->name('timer.stop');
                Route::post('review', [TimeEntryController::class, 'review'])
                    ->can(Permission::TimesheetApprove->value)
                    ->name('review');
                Route::put('{entry}', [TimeEntryController::class, 'update'])->name('update');
                Route::delete('{entry}', [TimeEntryController::class, 'destroy'])->name('destroy');
            });

            Route::get('quotes', [QuoteController::class, 'index'])->can(Permission::QuotesView->value)->name('quotes.index');
            Route::get('quotes/{quote}', [QuoteController::class, 'show'])->can(Permission::QuotesView->value)->name('quotes.show');
            Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->can(Permission::QuotesView->value)->name('quotes.pdf');
            Route::post('quotes', [QuoteController::class, 'store'])->can(Permission::QuotesManage->value)->name('quotes.store');
            Route::put('quotes/{quote}', [QuoteController::class, 'update'])->can(Permission::QuotesManage->value)->name('quotes.update');
            Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])->can(Permission::QuotesManage->value)->name('quotes.destroy');
            Route::post('quotes/{quote}/send', [QuoteController::class, 'send'])->can(Permission::QuotesManage->value)->name('quotes.send');
            Route::post('quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->can(Permission::QuotesManage->value)->name('quotes.duplicate');

            Route::get('contracts', [ContractController::class, 'index'])->can(Permission::QuotesView->value)->name('contracts.index');
            Route::get('contracts/{contract}', [ContractController::class, 'show'])->can(Permission::QuotesView->value)->name('contracts.show');
            Route::put('contracts/{contract}/status', [ContractController::class, 'close'])->can(Permission::QuotesManage->value)->name('contracts.close');

            // Minhas tarefas: qualquer membro vê as próprias, sem permissão extra.
            Route::get('tasks/mine', [TaskController::class, 'mine'])->name('tasks.mine');

            // A tarefa sempre é buscada dentro do projeto da URL (scopeBindings).
            Route::prefix('projects/{project}/tasks')->name('projects.tasks.')->scopeBindings()->group(function (): void {
                Route::get('/', [TaskController::class, 'index'])->can(Permission::ProjectsView->value)->name('index');
                Route::post('/', [TaskController::class, 'store'])->can(Permission::TasksManage->value)->name('store');
                Route::put('{task}', [TaskController::class, 'update'])->can(Permission::TasksManage->value)->name('update');
                Route::put('{task}/move', [TaskController::class, 'move'])->can(Permission::TasksManage->value)->name('move');
                Route::delete('{task}', [TaskController::class, 'destroy'])->can(Permission::TasksManage->value)->name('destroy');
            });
        });
    });
});

// Nome padrão do Laravel, usado pela notificação de verificação para montar o link assinado.
Route::get('v1/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:auth'])
    ->whereNumber('id')
    ->name('verification.verify');
