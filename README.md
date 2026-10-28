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
npm run test:e2e           # ponta a ponta no Chromium (Playwright)
```

Os testes ponta a ponta sobem sozinhos a API (porta 8001, com SQLite, fila síncrona e e-mails no log) e o front (porta 5174), sem precisar de Postgres nem Redis. Na primeira vez, instale o navegador com `npx playwright install chromium`.

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

## Documentação da API

A documentação OpenAPI é gerada a partir do código (rotas, FormRequests e Resources) pelo [Scramble](https://scramble.dedoc.co):

- Em desenvolvimento, com a API rodando: http://localhost:8000/docs/api (dá para testar as chamadas ali mesmo).
- O arquivo [`docs/openapi.json`](docs/openapi.json) fica no repositório para quem quiser importar no Postman, Insomnia ou gerar um cliente.

Depois de mudar rotas, validações ou Resources, gere o arquivo de novo (o CI avisa se ele ficar desatualizado):

```bash
cd backend
php artisan scramble:export
```

## Publicando em produção

O Kairo roda inteiro com Docker num servidor (uma VPS com 2 GB de RAM já serve). Os arquivos de produção:

| Arquivo | O que faz |
|---|---|
| `compose.prod.yaml` | Sobe tudo: Caddy (porta de entrada com HTTPS), API, fila, agendador, Reverb, Postgres e Redis |
| `backend/Dockerfile` | Imagem da API com FrankenPHP; a mesma imagem roda a fila, o agendador e o Reverb |
| `frontend/Dockerfile` | Build do front servido pelo Caddy, que repassa `/api` para a API e o WebSocket para o Reverb |
| `.env.production.example` | Variáveis que você precisa preencher |

Passo a passo:

1. Crie o servidor (Ubuntu 24.04, por exemplo), instale o Docker e libere as portas 80 e 443.
2. Aponte o domínio (registro DNS tipo A) para o IP do servidor.
3. No servidor, clone o repositório e prepare as variáveis:

   ```bash
   git clone https://github.com/Hugueninfer/Kairo.git && cd Kairo
   cp .env.production.example .env.production
   ```

   Preencha `APP_DOMAIN`, `APP_KEY`, `DB_PASSWORD`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` e o SMTP. Para gerar valores aleatórios: `openssl rand -hex 24` (e, para a `APP_KEY`, `echo "base64:$(openssl rand -base64 32)"`).
4. Suba:

   ```bash
   docker compose -f compose.prod.yaml --env-file .env.production up -d --build
   ```

   Na primeira subida, a API roda as migrations e o Caddy emite o certificado HTTPS. Em alguns minutos o Kairo responde em `https://seu-dominio`.
5. Para atualizar: `git pull` e o mesmo comando do passo 4.

Dicas:

- Logs: `docker compose -f compose.prod.yaml logs -f app queue`.
- Backup do banco: `docker compose -f compose.prod.yaml exec postgres pg_dump -U kairo kairo > backup.sql`. Vale agendar isso e guardar fora do servidor.
- Os PDFs e anexos ficam no volume `storage`; os dados do Postgres e do Redis também ficam em volumes, então sobrevivem às atualizações.
- Os pagamentos continuam simulados (`PAYMENT_GATEWAY=fake`) até um gateway real ser ligado.

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

## Segurança

- Sessão do Sanctum em cookie HttpOnly com CSRF; nada de token no `localStorage`. CORS só para o `FRONTEND_URL`.
- Isolamento entre empresas no middleware, no escopo global dos models e nos repositories, com teste de 404 entre empresas em cada recurso.
- Limite de tentativas no login (5 por e-mail e IP), no código de duas etapas, no cadastro, na recuperação de senha e nos links públicos.
- Login em duas etapas opcional (aplicativo autenticador, TOTP) na tela **Minha conta**, com 8 códigos de recuperação de uso único. O segredo fica criptografado no banco e um código não vale duas vezes.
- Senhas com letras maiúsculas, minúsculas e números; em produção, senhas que já vazaram são recusadas.
- Headers de segurança em todas as respostas da API (CSP, `X-Frame-Options`, `nosniff`, HSTS em HTTPS).
- Os CSVs exportados neutralizam fórmulas (texto começando com `=`, `+`, `-` ou `@`), para a planilha não executar o que alguém digitou.
