import { expect, test } from '@playwright/test'
import { logout, PASSWORD, signUp, uniqueEmail } from './support.ts'

test('cria a conta e a empresa, sai e entra de novo', async ({ page }) => {
  const email = uniqueEmail('ana')
  await signUp(page, 'Ana Souza', email)

  await logout(page)
  await page.goto('/')
  await page.waitForURL('**/login?redirect=/')

  await page.fill('#email', email)
  await page.fill('#password', 'senha-errada')
  await page.click('button[type=submit]')
  await expect(page.getByTestId('form-alert')).toHaveText('E-mail ou senha inválidos.')

  await page.fill('#password', PASSWORD)
  await page.click('button[type=submit]')
  await page.waitForURL((url) => url.pathname === '/')
  await expect(page.getByText('Você está em Estúdio E2E.')).toBeVisible()
})
