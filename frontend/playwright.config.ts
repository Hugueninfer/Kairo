import { defineConfig, devices } from '@playwright/test'

// Testes ponta a ponta: o front de verdade (Vite) falando com a API de verdade (Laravel).
// A API roda com SQLite, fila síncrona e e-mails no log, então não precisa de Postgres nem Redis.
// O cache em memória vale só para cada requisição: os limites de tentativas não passam de um
// teste para o outro (eles têm testes próprios no back-end).
// Portas próprias (8001 e 5174) para não brigar com o ambiente de desenvolvimento.
const API_PORT = 8001
const WEB_PORT = 5174

const apiEnv = {
  APP_ENV: 'local',
  APP_DEBUG: 'true',
  APP_URL: `http://localhost:${API_PORT}`,
  FRONTEND_URL: `http://localhost:${WEB_PORT}`,
  SANCTUM_STATEFUL_DOMAINS: `localhost:${WEB_PORT}`,
  SESSION_DOMAIN: 'localhost',
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: `${process.cwd()}/../backend/database/e2e.sqlite`,
  SESSION_DRIVER: 'file',
  CACHE_STORE: 'array',
  QUEUE_CONNECTION: 'sync',
  BROADCAST_CONNECTION: 'log',
  MAIL_MAILER: 'log',
}

export default defineConfig({
  testDir: './e2e',
  // Um teste por vez: todos usam a mesma API e o mesmo banco.
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  timeout: 60_000,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: `http://localhost:${WEB_PORT}`,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    testIdAttribute: 'data-test',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
      command: `touch database/e2e.sqlite && php artisan migrate:fresh --force && cd public && php -S 127.0.0.1:${API_PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
      cwd: '../backend',
      env: apiEnv,
      url: `http://127.0.0.1:${API_PORT}/up`,
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: `npx vite --port ${WEB_PORT} --strictPort`,
      env: { VITE_API_PROXY_TARGET: `http://127.0.0.1:${API_PORT}`, VITE_REVERB_APP_KEY: '' },
      url: `http://localhost:${WEB_PORT}`,
      reuseExistingServer: false,
      timeout: 120_000,
    },
  ],
})
