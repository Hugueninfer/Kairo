import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { tasksApi } from '@/services/projects'
import { reportsApi } from '@/services/reports'
import { useCompanyStore } from '@/stores/company'
import type { Dashboard } from '@/types/api'
import DashboardView from '@/views/DashboardView.vue'

const overview: Dashboard = {
  month: 'Outubro de 2026',
  hours: { mine: { total: 300, billable: 240 }, team: null },
  finance: {
    received_this_month: '2500.00',
    open_count: 2,
    open_total: '1800.00',
    overdue_count: 1,
    overdue_total: '600.00',
    revenue: [
      { month: '2026-09', label: 'set', total: '1000.00' },
      { month: '2026-10', label: 'out', total: '2500.00' },
    ],
  },
  projects_over_budget: [{ id: 'p1', name: 'Site da padaria', budget: '1000.00', consumed: '1100.00', percent: 110 }],
}

async function mountView(data: Dashboard) {
  setActivePinia(createPinia())
  useCompanyStore().access = { role: null, permissions: [] }
  vi.spyOn(tasksApi, 'mine').mockResolvedValue([])
  vi.spyOn(reportsApi, 'dashboard').mockResolvedValue(data)
  const router = createRouter({ history: createMemoryHistory(), routes: [
      { path: '/projetos/:id', name: 'project-board', component: { template: '<div />' } },
      { path: '/:any(.*)', component: { template: '<div />' } },
    ],
  })
  const wrapper = mount(DashboardView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('DashboardView', () => {
  beforeEach(() => vi.restoreAllMocks())

  it('mostra o financeiro do mês, a receita e os projetos estourados', async () => {
    const wrapper = await mountView(overview)

    expect(wrapper.get('[data-test="received"]').text()).toContain('2.500,00')
    expect(wrapper.get('[data-test="overdue"]').text()).toContain('1 fatura vencida')
    expect(wrapper.get('[data-test="revenue"] [role="img"]').attributes('aria-label')).toContain('out: R$')
    expect(wrapper.get('[data-test="over-budget"]').text()).toContain('Estourou · 110%')
    expect(wrapper.get('[data-test="my-hours"]').text()).toContain('5:00 h')
  })

  it('esconde o financeiro de quem não pode ver faturas', async () => {
    const wrapper = await mountView({ ...overview, finance: null, projects_over_budget: null })

    expect(wrapper.find('[data-test="received"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="revenue"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="over-budget"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="my-hours"]').exists()).toBe(true)
  })
})
