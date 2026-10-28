import { expect, test } from '@playwright/test'
import { api, signUp, uniqueEmail } from './support.ts'

test('do cliente às horas: painel, relatório e histórico', async ({ page }) => {
  await signUp(page, 'Bruno Lima', uniqueEmail('bruno'))

  // Cliente pela tela.
  await page.getByRole('link', { name: 'Clientes' }).click()
  await page.getByRole('button', { name: 'Novo cliente' }).click()
  await page.fill('#client-name', 'Padaria Pão Quente')
  await page.fill('#client-email', 'contato@paoquente.test')
  await page.getByRole('button', { name: 'Salvar' }).click()
  await expect(page.getByTestId('clients')).toContainText('Padaria Pão Quente')

  // Projeto e horas pela API, com a mesma sessão.
  const clients = await api<{ data: { id: string }[] }>(page, 'GET', '/api/v1/company/clients')
  const project = await api<{ data: { id: string } }>(page, 'POST', '/api/v1/company/projects', {
    name: 'Site da padaria',
    client_id: clients.data[0]!.id,
    hourly_rate: 150,
    budget: 1000,
  })
  const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'America/Sao_Paulo' })
  await api(page, 'POST', '/api/v1/company/time-entries', { project_id: project.data.id, date: today, minutes: 360, billable: true })

  // Painel: horas do mês e o projeto passando de 80% do orçamento (6h × R$ 150 = R$ 900 de R$ 1.000).
  await page.getByRole('link', { name: 'Painel' }).click()
  await expect(page.getByTestId('my-hours')).toContainText('6:00 h')
  await expect(page.getByTestId('over-budget')).toContainText('Site da padaria')
  await expect(page.getByTestId('over-budget')).toContainText('90%')

  // Relatório por cliente, com o CSV.
  await page.getByRole('link', { name: 'Relatórios' }).click()
  await page.getByRole('tab', { name: 'Por cliente' }).click()
  await expect(page.getByTestId('report')).toContainText('Padaria Pão Quente')
  await expect(page.getByTestId('total-hours')).toHaveText('6:00')
  const [download] = await Promise.all([page.waitForEvent('download'), page.getByTestId('download-csv').click()])
  expect(download.suggestedFilename()).toMatch(/^horas-client-.*\.csv$/)

  // Histórico: quem criou o cliente e o projeto.
  await page.getByRole('link', { name: 'Histórico' }).click()
  await expect(page.getByTestId('audit-list')).toContainText('Bruno Lima criou o cliente Padaria Pão Quente')
  await expect(page.getByTestId('audit-list')).toContainText('Bruno Lima criou o projeto Site da padaria')
})
