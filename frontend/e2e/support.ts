import { createHmac } from 'node:crypto'
import { expect, type Page } from '@playwright/test'

export const PASSWORD = 'SenhaForte123'

/** E-mail único por teste, para os testes não dependerem uns dos outros. */
export function uniqueEmail(name: string): string {
  return `${name}.${Date.now()}.${Math.floor(Math.random() * 1000)}@kairo.test`
}

/** Cria a conta e a primeira empresa pelas telas, e termina no painel. */
export async function signUp(page: Page, name: string, email: string, company = 'Estúdio E2E'): Promise<void> {
  await page.goto('/cadastro')
  await page.fill('#name', name)
  await page.fill('#email', email)
  await page.fill('#password', PASSWORD)
  await page.fill('#password_confirmation', PASSWORD)
  await page.click('button[type=submit]')
  await page.waitForURL('**/empresas/nova')

  await page.fill('#name', company)
  await page.fill('#default_hourly_rate', '150')
  await page.click('button[type=submit]')
  await page.waitForURL((url) => url.pathname === '/')
  await expect(page.getByRole('heading', { name: `Olá, ${name}!` })).toBeVisible()
}

export async function logout(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Sair' }).click()
  await page.waitForURL('**/login')
}

/**
 * Chama a API com a sessão do navegador (mesmos cookies e CSRF do front),
 * para montar dados que não são o foco do teste.
 */
export async function api<T = unknown>(page: Page, method: 'GET' | 'POST' | 'PUT' | 'DELETE', url: string, body?: unknown): Promise<T> {
  const cookies = await page.context().cookies()
  const xsrf = decodeURIComponent(cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '')
  const response = await page.request.fetch(url, {
    method,
    data: body,
    // O Sanctum só usa a sessão do cookie quando a requisição diz que vem do front.
    headers: { Accept: 'application/json', Referer: new URL(page.url()).origin, 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' },
  })
  expect(response.ok(), `${method} ${url}: ${response.status()} ${await response.text()}`).toBeTruthy()
  return (response.status() === 204 ? null : await response.json()) as T
}

/** Código TOTP de 6 dígitos (RFC 6238), o mesmo que o aplicativo autenticador mostraria. */
export function totp(secret: string, at = Date.now()): string {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
  let bits = ''
  for (const char of secret.replace(/=+$/, '').toUpperCase()) {
    bits += alphabet.indexOf(char).toString(2).padStart(5, '0')
  }
  const key = Buffer.from(bits.match(/.{8}/g)!.map((byte) => parseInt(byte, 2)))

  const counter = Buffer.alloc(8)
  counter.writeBigUInt64BE(BigInt(Math.floor(at / 1000 / 30)))
  const hmac = createHmac('sha1', key).update(counter).digest()
  const offset = hmac[hmac.length - 1]! & 0xf
  const value = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000

  return value.toString().padStart(6, '0')
}
