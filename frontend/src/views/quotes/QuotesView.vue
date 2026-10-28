<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import UiPagination from '@/components/ui/UiPagination.vue'
import QuotesTabs from '@/components/quotes/QuotesTabs.vue'
import { formatMoney } from '@/composables/money'
import { statusTone } from '@/composables/statusTone'
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
const currentPage = ref(1)
const loadError = ref<string | null>(null)

async function load() {
  try {
    page.value = await quotesApi.list({ status: status.value || undefined, search: search.value || undefined, page: currentPage.value })
  } catch (error) {
    loadError.value = toApiError(error).message
  }
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => goTo(1), 300)
})
watch(status, () => goTo(1))

function goTo(pageNumber: number) {
  currentPage.value = pageNumber
  load()
}
onMounted(() => Promise.all([load(), loadOptions()]))
</script>

<template>
  <AppLayout>
    <PageHeader title="Orçamentos" subtitle="Propostas enviadas aos clientes e os contratos que nasceram delas." :breadcrumbs="[{ label: 'Comercial' }, { label: 'Orçamentos' }]">
      <template #actions>
        <RouterLink v-if="canManage" :to="{ name: 'quote-new' }" class="btn-primary"><AppIcon name="plus" :size="16" />Novo orçamento</RouterLink>
      </template>
      <template #tabs><QuotesTabs /></template>
    </PageHeader>

    <FormAlert :message="loadError" />

    <section class="card overflow-hidden">
      <div class="card-header">
        <div class="relative w-full sm:w-72">
          <AppIcon name="search" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-fg-subtle" />
          <input v-model="search" type="search" placeholder="Buscar por título ou número" aria-label="Buscar" class="field field-sm w-full pl-9" />
        </div>
        <select v-model="status" aria-label="Situação" class="field field-sm w-full sm:w-52">
          <option value="">Todas as situações</option>
          <option v-for="option in options?.quote_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <EmptyState v-if="page && page.data.length === 0" icon="file" title="Nenhum orçamento por aqui." />
      <div v-else class="overflow-x-auto">
        <table class="table-clean">
          <thead>
            <tr>
              <th>Orçamento</th>
              <th class="hidden md:table-cell">Cliente</th>
              <th class="hidden sm:table-cell">Validade</th>
              <th class="text-right">Valor</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody data-test="quotes">
            <tr v-for="quote in page?.data ?? []" :key="quote.id" class="cursor-pointer" @click="$router.push({ name: 'quote-edit', params: { id: quote.id } })">
              <td class="sm:min-w-56">
                <RouterLink :to="{ name: 'quote-edit', params: { id: quote.id } }" class="font-medium text-fg hover:text-brand-300" @click.stop>{{ quote.title }}</RouterLink>
                <p class="font-mono text-xs text-fg-muted">{{ quote.number }}<span class="md:hidden"> · {{ quote.client?.name }}</span></p>
              </td>
              <td class="hidden text-fg-soft md:table-cell">{{ quote.client?.name }}</td>
              <td class="hidden text-fg-muted sm:table-cell">{{ new Date(`${quote.valid_until}T12:00:00`).toLocaleDateString('pt-BR') }}</td>
              <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ formatMoney(quote.total, company.current?.currency) }}</td>
              <td><StatusBadge :tone="statusTone('quote', quote.status.value)">{{ quote.status.label }}</StatusBadge></td>
            </tr>
          </tbody>
        </table>
      </div>
      <UiPagination v-if="page && page.meta.last_page > 1" :current="page.meta.current_page" :last="page.meta.last_page" @go="goTo" />
    </section>
  </AppLayout>
</template>
