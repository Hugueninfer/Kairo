<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import QuotesTabs from '@/components/quotes/QuotesTabs.vue'
import { formatMoney } from '@/composables/money'
import { useOptions } from '@/composables/useOptions'
import AppLayout from '@/layouts/AppLayout.vue'
import { toApiError } from '@/services/http'
import { quotesApi } from '@/services/quotes'
import { useCompanyStore } from '@/stores/company'
import type { Paginated, Quote } from '@/types/api'

const company = useCompanyStore()
const canManage = computed(() => company.can('quotes.manage'))
const { options, load: loadOptions } = useOptions()

const page = ref<Paginated<Quote> | null>(null)
const status = ref('')
const search = ref('')
const loadError = ref<string | null>(null)

const badge: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  sent: 'bg-sky-50 text-sky-700',
  viewed: 'bg-indigo-50 text-indigo-700',
  accepted: 'bg-emerald-50 text-emerald-700',
  rejected: 'bg-red-50 text-red-700',
  expired: 'bg-amber-50 text-amber-700',
}

async function load() {
  try {
    page.value = await quotesApi.list({ status: status.value || undefined, search: search.value || undefined })
  } catch (error) {
    loadError.value = toApiError(error).message
  }
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})
watch(status, load)
onMounted(() => Promise.all([load(), loadOptions()]))
</script>

<template>
  <AppLayout>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Orçamentos</h1>
      <RouterLink
        v-if="canManage"
        :to="{ name: 'quote-new' }"
        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"
      >
        Novo orçamento
      </RouterLink>
    </div>

    <QuotesTabs />

    <div class="flex flex-wrap items-center gap-3 text-sm">
      <input v-model="search" type="search" placeholder="Buscar por título ou número" aria-label="Buscar" class="w-64 rounded-lg border border-slate-300 px-3 py-1.5" />
      <select v-model="status" aria-label="Situação" class="rounded-lg border border-slate-300 px-2 py-1.5">
        <option value="">Todas as situações</option>
        <option v-for="option in options?.quote_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
    </div>

    <FormAlert :message="loadError" />

    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <p v-if="page && page.data.length === 0" class="p-6 text-sm text-slate-500">Nenhum orçamento por aqui.</p>
      <ul class="divide-y divide-slate-100" data-test="quotes">
        <li v-for="quote in page?.data ?? []" :key="quote.id">
          <RouterLink :to="{ name: 'quote-edit', params: { id: quote.id } }" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50">
            <span class="w-28 font-mono text-xs text-slate-500">{{ quote.number }}</span>
            <div class="min-w-48 flex-1">
              <p class="font-medium">{{ quote.title }}</p>
              <p class="text-xs text-slate-500">{{ quote.client?.name }}</p>
            </div>
            <span class="text-xs text-slate-500">válido até {{ new Date(`${quote.valid_until}T12:00:00`).toLocaleDateString('pt-BR') }}</span>
            <span class="w-28 text-right font-semibold tabular-nums">{{ formatMoney(quote.total, company.current?.currency) }}</span>
            <span class="w-24 rounded px-2 py-0.5 text-center text-xs" :class="badge[quote.status.value]">{{ quote.status.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
