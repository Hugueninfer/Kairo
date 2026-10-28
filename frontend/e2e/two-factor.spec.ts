import { expect, test } from '@playwright/test'
import { logout, PASSWORD, signUp, totp, uniqueEmail } from './support.ts'

test('liga o login em duas etapas e passa a pedir o código ao entrar', async ({ page }) => {
  const email = uniqueEmail('carla')
  await signUp(page, 'Carla Dias', email)

  await page.getByRole('link', { name: 'Carla Dias' }).click()
  await page.fill('#confirm_password', PASSWORD)
  await page.getByRole('button', { name: 'Configurar o aplicativo' }).click()
  await expect(page.getByTestId('qr')).toBeVisible()

  const secret = (await page.locator('[data-test="two-factor"] code').textContent())!.trim()
  // Código da janela anterior: o próximo login usa o da janela atual, que ainda não foi usado.
  await page.fill('#two_factor_code', totp(secret, Date.now() - 30_000))
  await page.getByRole('button', { name: 'Ligar' }).click()
  await expect(page.getByTestId('recovery-codes')).toBeVisible()
  await expect(page.getByTestId('two-factor-status')).toHaveText('Ligado')

  await logout(page)
  await page.fill('#email', email)
  await page.fill('#password', PASSWORD)
  await page.click('button[type=submit]')
  await expect(page.getByRole('heading', { name: 'Código de verificação' })).toBeVisible()

  await page.fill('#code', '000000')
  await page.click('button[type=submit]')
  await expect(page.getByTestId('form-alert')).toContainText('Código inválido')

  await page.fill('#code', totp(secret))
  await page.click('button[type=submit]')
  await page.waitForURL((url) => url.pathname === '/')
  await expect(page.getByRole('heading', { name: 'Olá, Carla Dias!' })).toBeVisible()
})
