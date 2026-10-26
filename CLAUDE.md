# Kairo: regras do projeto

- Idioma: código e nomes em inglês; comentários, mensagens de commit, README e textos da interface em português.
- Monorepo: `backend/` (Laravel 13) e `frontend/` (Vue 3 + TypeScript).
- O front só fala com a API (`/api/v1`). Nada de acesso direto ao banco nem segredos no front.
- Autenticação: Sanctum em modo SPA (cookie HttpOnly + CSRF). Não guardar tokens no localStorage.

## Camadas do back-end

- Controller: recebe FormRequest, monta DTO, chama um método do Service, devolve API Resource. Poucas linhas.
- Service: regra de negócio e orquestração. Não usa Eloquent, query builder nem a facade DB.
- Repository: todo acesso ao banco, via prettus/l5-repository. Interface em `app/Repositories/Contracts`, implementação em `app/Repositories/Eloquent`, ligação no `RepositoryServiceProvider`. Filtros reutilizáveis viram Criteria em `app/Criteria`.
- Model: relacionamentos, casts (Enums) e escopo de tenant. Sem regra de negócio.
- Enums em `app/Enums` com casts nos models.

## Antes de entregar

- backend: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `vendor/bin/pest`
- frontend: `npm run type-check`, `npm test`, `npm run build`
