# Kairo

Plataforma SaaS multi-tenant de gestão para autônomos, freelancers e pequenas agências: clientes, orçamentos e contratos, projetos e tarefas, planilha de horas, faturas com link de pagamento, cobrança recorrente e boards colaborativos.

## Estrutura

```
backend/    API em Laravel 13 (Sanctum SPA, l5-repository, Pest, Larastan)
frontend/   SPA em Vue 3 + TypeScript (Vite, Pinia, Vue Router, Axios)
compose.yaml  Postgres, Redis e Mailpit para desenvolvimento
```

O front nunca acessa o banco: tudo passa pela API em `/api/v1`.

## Rodando localmente

Requisitos: PHP 8.3+, Composer, Node 22+ e Docker.

```bash
# 1. Serviços de apoio
docker compose up -d

# 2. API
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve          # http://localhost:8000
php artisan queue:work --queue=mail,default   # em outro terminal: e-mails, versões e avisos dos boards
php artisan reverb:start   # em outro terminal: WebSockets dos boards (ws://localhost:8080)

# 3. Front (em outro terminal)
cd frontend
npm install
cp .env.example .env
npm run dev                # http://localhost:5173
```

Os e-mails enviados em desenvolvimento aparecem no Mailpit: http://localhost:8025

## Qualidade

```bash
# backend/
vendor/bin/pint --test     # padrão de código
vendor/bin/phpstan analyse # análise estática
vendor/bin/pest            # testes

# frontend/
npm run type-check
npm test
npm run build
```

O GitHub Actions roda tudo isso em cada PR.

## Arquitetura do back-end

Route → Middleware → FormRequest → Controller → DTO → Service → Repository → Model, com a resposta saindo por um API Resource.

- **Controllers** são finos: recebem o FormRequest, chamam o Service e devolvem um Resource.
- **Services** têm a regra de negócio e não usam Eloquent nem query builder.
- **Repositories** (prettus/l5-repository) concentram todo o acesso ao banco.

As regras de camada são checadas por testes de arquitetura em `backend/tests/Arch`.

## Multi-tenant e permissões

- Cada empresa é um tenant. A empresa ativa vem do header `X-Company-Id` (ULID) ou da última empresa escolhida, e o middleware `tenant` sempre confere se o usuário é membro.
- Papéis e permissões usam spatie/laravel-permission em modo teams, com `company_id` como time. Toda empresa nasce com os papéis Dono, Administrador, Financeiro e Membro.
- O catálogo de permissões fica no enum `App\Enums\Permission`. As rotas exigem a permissão com `->can(...)`, e o dono da empresa passa em todas.

## Boards em tempo real

- O WebSocket é o Laravel Reverb. Cada board tem um canal de presença `board.{ulid}`, autorizado em `POST /api/v1/company/broadcasting/auth` com a mesma sessão, CSRF e filtro de empresa das outras rotas: só entra quem é da empresa dona do board e tem `boards.view`.
- O autosave grava pela API e o back avisa os outros pelo canal (pela fila). Lotes grandes demais para uma mensagem viram só um aviso para recarregar. Os cursores vão direto entre os navegadores (whisper), sem passar pelo banco.
- Versões: uma automática a cada 10 minutos de edição (no máximo 50 por board) e as salvas com nome, todas tiradas na fila. Restaurar guarda antes o estado atual.
- O link de leitura para o cliente (`/b/{token}`) abre sem login e pode ser desligado a qualquer momento. Quem abre por ele não entra no canal ao vivo; a página se atualiza a cada 30 segundos.
- Sem `VITE_REVERB_APP_KEY` no front, os boards funcionam normalmente, só sem colaboração ao vivo.


## Painel, relatórios e histórico

- O painel (`GET /api/v1/company/dashboard`) mostra só o que o papel permite: as horas da pessoa sempre; as da equipe com `timesheet.view_all`; recebido, a receber, vencido, a receita dos últimos 6 meses e os projetos perto de estourar o orçamento com `invoices.view`. Fica em cache por 60 segundos.
- O relatório de horas (`GET /api/v1/company/reports/hours`, por projeto, pessoa ou cliente) é calculado na fila. Enquanto não fica pronto, a API responde `202` e o front pergunta de novo a cada segundo. O resultado fica em cache e é recalculado sozinho quando alguma hora muda. O CSV é gerado no navegador.
- O histórico de alterações (`GET /api/v1/company/audit`, permissão `audit.view`) usa o spatie/laravel-activitylog: clientes, projetos, tarefas, orçamentos, contratos, faturas, pagamentos, boards, papéis e membros. Cada registro guarda a empresa, quem fez e os campos que mudaram. Registros com mais de um ano são apagados todo dia às 03:30 (`activitylog:clean`).
