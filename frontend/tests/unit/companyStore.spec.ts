import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { companiesApi } from '@/services/companies'
import { useCompanyStore } from '@/stores/company'
import type { Company } from '@/types/api'

function company(id: string, name: string, isCurrent = false): Company {
  return {
    id,
    name,
    document: null,
    currency: 'BRL',
    timezone: 'America/Sao_Paulo',
    default_hourly_rate: null,
    is_owner: true,
    is_current: isCurrent,
  }
}

describe('company store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('identifica a empresa ativa', async () => {
    vi.spyOn(companiesApi, 'access').mockResolvedValue({ role: null, permissions: [] })
    vi.spyOn(companiesApi, 'list').mockResolvedValue([company('a', 'Alfa'), company('b', 'Beta', true)])
    const store = useCompanyStore()

    await store.load()

    expect(store.current?.id).toBe('b')
  })

  it('troca a empresa ativa sem deixar duas marcadas', async () => {
    vi.spyOn(companiesApi, 'access').mockResolvedValue({ role: null, permissions: [] })
    vi.spyOn(companiesApi, 'list').mockResolvedValue([company('a', 'Alfa', true), company('b', 'Beta')])
    vi.spyOn(companiesApi, 'switchTo').mockResolvedValue(company('b', 'Beta', true))
    const store = useCompanyStore()
    await store.load()

    await store.switchTo('b')

    expect(store.current?.id).toBe('b')
    expect(store.companies.filter((item) => item.is_current)).toHaveLength(1)
  })

  it('a empresa criada vira a ativa', async () => {
    vi.spyOn(companiesApi, 'access').mockResolvedValue({ role: { id: 1, name: 'Dono' }, permissions: ['roles.manage'] })
    vi.spyOn(companiesApi, 'create').mockResolvedValue(company('c', 'Nova', true))
    const store = useCompanyStore()

    await store.create({ name: 'Nova' })

    expect(store.current?.name).toBe('Nova')
  })

  it('libera na tela só as permissões do papel na empresa ativa', async () => {
    vi.spyOn(companiesApi, 'list').mockResolvedValue([company('a', 'Alfa', true), company('b', 'Beta')])
    const access = vi
      .spyOn(companiesApi, 'access')
      .mockResolvedValueOnce({ role: { id: 1, name: 'Administrador' }, permissions: ['members.invite'] })
      .mockResolvedValueOnce({ role: { id: 9, name: 'Membro' }, permissions: ['members.view'] })
    vi.spyOn(companiesApi, 'switchTo').mockResolvedValue(company('b', 'Beta', true))
    const store = useCompanyStore()

    await store.load()
    expect(store.can('members.invite')).toBe(true)

    await store.switchTo('b')
    expect(access).toHaveBeenCalledTimes(2)
    expect(store.can('members.invite')).toBe(false)
    expect(store.can('members.view')).toBe(true)
  })

  it('sem empresa carregada, nada é permitido', () => {
    expect(useCompanyStore().can('members.view')).toBe(false)
  })
})
