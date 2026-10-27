<?php

namespace App\Providers;

use App\Models\User;
use App\Support\TenantContext;
use App\Support\Transactions\DatabaseTransactionManager;
use App\Support\Transactions\TransactionManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Uma empresa ativa por requisição (ou por job).
        $this->app->scoped(TenantContext::class);

        $this->app->bind(TransactionManager::class, DatabaseTransactionManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Em desenvolvimento, falha cedo em lazy loading, atributos inexistentes e mass assignment silencioso.
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->configureRateLimiting();
        $this->configurePasswords();
        $this->configureAuthorization();
    }

    private function configureAuthorization(): void
    {
        // O dono da empresa ativa pode tudo nela, independentemente do papel.
        Gate::before(function (User $user): ?bool {
            $tenant = $this->app->make(TenantContext::class);

            return $tenant->has() && $tenant->company()->isOwnedBy($user) ? true : null;
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Cadastro, recuperação de senha e reenvio de e-mail: rotas sensíveis a abuso.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        // Links públicos (orçamento aberto pelo cliente): sem login, então limita por IP.
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }

    private function configurePasswords(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->mixedCase()->numbers();

            // Em produção, também recusa senhas que já vazaram (consulta anônima ao Have I Been Pwned).
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // O link do e-mail aponta para a tela do front, que chama a API com o token.
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return rtrim((string) config('app.frontend_url'), '/').'/redefinir-senha?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
