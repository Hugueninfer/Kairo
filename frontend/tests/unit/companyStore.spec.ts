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
    vi.spyOn(companiesApi, 'list').mockResolvedValue([company('a', 'Alfa'), company('b', 'Beta', true)])
    const store = useCompanyStore()

    await store.load()

    expect(store.current?.id).toBe('b')
  })

  it('troca a empresa ativa sem deixar duas marcadas', async () => {
    vi.spyOn(companiesApi, 'list').mockResolvedValue([company('a', 'Alfa', true), company('b', 'Beta')])
    vi.spyOn(companiesApi, 'switchTo').mockResolvedValue(company('b', 'Beta', true))
    const store = useCompanyStore()
    await store.load()

    await store.switchTo('b')

    expect(store.current?.id).toBe('b')
    expect(store.companies.filter((item) => item.is_current)).toHaveLength(1)
  })

  it('a empresa criada vira a ativa', async () => {
    vi.spyOn(companiesApi, 'create').mockResolvedValue(company('c', 'Nova', true))
    const store = useCompanyStore()

    await store.create({ name: 'Nova' })

    expect(store.current?.name).toBe('Nova')
  })
})
