import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { companiesApi, type CompanyPayload } from '@/services/companies'
import type { Company } from '@/types/api'

export const useCompanyStore = defineStore('company', () => {
  const companies = ref<Company[]>([])
  const loaded = ref(false)

  const current = computed(() => companies.value.find((company) => company.is_current) ?? null)

  function markCurrent(company: Company) {
    const others = companies.value.filter((item) => item.id !== company.id).map((item) => ({ ...item, is_current: false }))
    companies.value = [...others, { ...company, is_current: true }].sort((a, b) => a.name.localeCompare(b.name))
  }

  async function load(): Promise<void> {
    companies.value = await companiesApi.list()
    loaded.value = true
  }

  async function create(payload: CompanyPayload): Promise<Company> {
    const company = await companiesApi.create(payload)
    markCurrent(company)
    return company
  }

  async function switchTo(id: string): Promise<void> {
    markCurrent(await companiesApi.switchTo(id))
  }

  function replaceCurrent(company: Company) {
    markCurrent(company)
  }

  function reset() {
    companies.value = []
    loaded.value = false
  }

  return { companies, loaded, current, load, create, switchTo, replaceCurrent, reset }
})
