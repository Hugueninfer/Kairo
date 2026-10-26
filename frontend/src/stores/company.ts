import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { companiesApi, type CompanyPayload } from '@/services/companies'
import type { Company, CompanyAccess } from '@/types/api'

export const useCompanyStore = defineStore('company', () => {
  const companies = ref<Company[]>([])
  const loaded = ref(false)
  const access = ref<CompanyAccess | null>(null)

  const current = computed(() => companies.value.find((company) => company.is_current) ?? null)

  /**
   * Só decide o que mostrar na tela. Quem garante a permissão de verdade é a API.
   */
  function can(permission: string): boolean {
    return access.value?.permissions.includes(permission) ?? false
  }

  function markCurrent(company: Company) {
    const others = companies.value.filter((item) => item.id !== company.id).map((item) => ({ ...item, is_current: false }))
    companies.value = [...others, { ...company, is_current: true }].sort((a, b) => a.name.localeCompare(b.name))
  }

  async function loadAccess(): Promise<void> {
    access.value = await companiesApi.access()
  }

  async function load(): Promise<void> {
    companies.value = await companiesApi.list()
    loaded.value = true
    access.value = current.value ? await companiesApi.access() : null
  }

  async function create(payload: CompanyPayload): Promise<Company> {
    const company = await companiesApi.create(payload)
    markCurrent(company)
    await loadAccess()
    return company
  }

  async function switchTo(id: string): Promise<void> {
    markCurrent(await companiesApi.switchTo(id))
    await loadAccess()
  }

  function replaceCurrent(company: Company) {
    markCurrent(company)
  }

  function reset() {
    companies.value = []
    loaded.value = false
    access.value = null
  }

  return { companies, loaded, access, current, can, load, loadAccess, create, switchTo, replaceCurrent, reset }
})
