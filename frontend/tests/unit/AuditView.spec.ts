import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { companiesApi } from '@/services/companies'
import { projectsApi } from '@/services/projects'
import { reportsApi } from '@/services/reports'
import { useCompanyStore } from '@/stores/company'
import type { AuditEntry, Options } from '@/types/api'
import AuditView from '@/views/reports/AuditView.vue'

const entry: AuditEntry = {
  id: 1,
  created_at: '2026-10-27T15:00:00Z',
  subject: { value: 'invoice', label: 'Faturas' },
  causer: { id: 7, name: 'Ana' },
  summary: 'alterou a fatura 2026-0004',
  changes: [{ field: 'status', label: 'Situação', old: 'Enviada', new: 'Paga' }],
}

async function mountView(permissions: string[]) {
  setActivePinia(createPinia())
  useCompanyStore().access = { role: null, permissions }
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:any(.*)', component: { template: '<div />' } }] })
  const wrapper = mount(AuditView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('AuditView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.spyOn(projectsApi, 'options').mockResolvedValue({ audit_subjects: [{ value: 'invoice', label: 'Faturas' }] } as unknown as Options)
  })

  it('mostra quem fez o quê e o que mudou', async () => {
    vi.spyOn(reportsApi, 'audit').mockResolvedValue({ data: [entry], meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 } })
    const wrapper = await mountView(['audit.view'])

    const row = wrapper.get('[data-test="audit-list"] li').text()
    expect(row).toContain('Ana alterou a fatura 2026-0004')
    expect(row).toContain('Situação:')
    expect(row).toContain('Enviada')
    expect(row).toContain('Paga')
    expect(wrapper.find('[data-test="filter-user"]').exists()).toBe(false)
  })

  it('filtra por tipo e por pessoa', async () => {
    vi.spyOn(companiesApi, 'members').mockResolvedValue([{ id: 7, name: 'Ana', email: 'ana@x.com', is_owner: false, role: null }])
    const audit = vi.spyOn(reportsApi, 'audit').mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, per_page: 30, total: 0 } })
    const wrapper = await mountView(['audit.view', 'members.view'])

    await wrapper.get('[data-test="filter-subject"]').setValue('invoice')
    await wrapper.get('[data-test="filter-user"]').setValue(7)
    await flushPromises()

    expect(audit).toHaveBeenLastCalledWith(expect.objectContaining({ subject: 'invoice', user: 7, page: 1 }))
    expect(wrapper.text()).toContain('Nenhuma alteração encontrada')
  })
})
