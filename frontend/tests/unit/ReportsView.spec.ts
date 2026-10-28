import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { reportsApi } from '@/services/reports'
import { useCompanyStore } from '@/stores/company'
import type { HoursReport } from '@/types/api'
import ReportsView from '@/views/reports/ReportsView.vue'

const report: HoursReport = {
  from: '2026-10-01',
  to: '2026-10-31',
  group: 'project',
  generated_at: '2026-10-28T12:00:00Z',
  rows: [
    { key: 'p1', label: 'Site da padaria', minutes: 600, billable_minutes: 540, amount: '1350.00' },
    { key: 'p2', label: 'App "Delivery"', minutes: 90, billable_minutes: 0, amount: '0.00' },
  ],
  totals: { minutes: 690, billable_minutes: 540, amount: '1350.00' },
}

async function mountView() {
  setActivePinia(createPinia())
  useCompanyStore().access = { role: null, permissions: ['reports.view'] }
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:any(.*)', component: { template: '<div />' } }] })
  const wrapper = mount(ReportsView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('ReportsView', () => {
  beforeEach(() => vi.restoreAllMocks())
  afterEach(() => vi.useRealTimers())

  it('pergunta de novo enquanto o relatório é calculado na fila', async () => {
    vi.useFakeTimers()
    const hours = vi
      .spyOn(reportsApi, 'hours')
      .mockResolvedValueOnce({ status: 'processing', data: null })
      .mockResolvedValueOnce({ status: 'ready', data: report })
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="processing"]').exists()).toBe(true)
    await vi.advanceTimersByTimeAsync(1000)
    await flushPromises()

    expect(hours).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-test="processing"]').exists()).toBe(false)
    expect(wrapper.get('[data-test="report"]').text()).toContain('Site da padaria')
    expect(wrapper.get('[data-test="total-hours"]').text()).toBe('11:30')
  })

  it('troca o agrupamento e busca de novo', async () => {
    const hours = vi.spyOn(reportsApi, 'hours').mockResolvedValue({ status: 'ready', data: report })
    const wrapper = await mountView()

    await wrapper.get('[data-group="member"]').trigger('click')
    await flushPromises()

    expect(hours).toHaveBeenLastCalledWith(expect.objectContaining({ group: 'member' }))
    expect(wrapper.get('thead').text()).toContain('Pessoa')
  })

  it('gera o CSV com ponto e vírgula e decimais com vírgula', async () => {
    vi.spyOn(reportsApi, 'hours').mockResolvedValue({ status: 'ready', data: report })
    const created: Blob[] = []
    URL.createObjectURL = vi.fn((blob: Blob) => {
      created.push(blob)
      return 'blob:csv'
    })
    URL.revokeObjectURL = vi.fn()
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
    const wrapper = await mountView()

    await wrapper.get('[data-test="download-csv"]').trigger('click')

    expect(click).toHaveBeenCalled()
    const csv = await created[0]!.text()
    expect(csv).toContain('"Projeto";"Horas";"Horas faturáveis";"Valor"')
    expect(csv).toContain('"Site da padaria";"10,00";"9,00";"1350,00"')
    expect(csv).toContain('"App ""Delivery""";"1,50";"0,00";"0,00"')
    expect(csv).toContain('"Total";"11,50";"9,00";"1350,00"')
  })
})
