<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import RecurrenceModal from '@/components/billing/RecurrenceModal.vue'
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
import { contractsApi } from '@/services/quotes'
import { useCompanyStore } from '@/stores/company'
import type { Contract, Paginated } from '@/types/api'

const company = useCompanyStore()
const canManage = computed(() => company.can('quotes.manage'))
const canBill = computed(() => company.can('invoices.manage'))
const { options, load: loadOptions } = useOptions()

const page = ref<Paginated<Contract> | null>(null)
const status = ref('active')
const currentPage = ref(1)
const error = ref<string | null>(null)
const editing = ref<Contract | null>(null)
const date = (value: string) => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR')

function onRecurrenceSaved(updated: Contract) {
  editing.value = null
  if (page.value) page.value.data = page.value.data.map((item) => (item.id === updated.id ? updated : item))
}

async function load() {
  error.value = null
  try {
    page.value = await contractsApi.list({ status: status.value || undefined, page: currentPage.value })
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

async function close(contract: Contract, next: 'completed' | 'cancelled') {
  const verb = next === 'completed' ? 'Concluir' : 'Cancelar'
  if (!window.confirm(`${verb} o contrato ${contract.number}?`)) return
  try {
    await contractsApi.close(contract.id, next)
    await load()
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

watch(status, () => goTo(1))

function goTo(pageNumber: number) {
  currentPage.value = pageNumber
  load()
}
onMounted(() => Promise.all([load(), loadOptions()]))
</script>

<template>
  <AppLayout>
    <PageHeader title="Orçamentos" subtitle="Propostas enviadas aos clientes e os contratos que nasceram delas." :breadcrumbs="[{ label: 'Comercial' }, { label: 'Contratos' }]">
      <template #tabs><QuotesTabs /></template>
    </PageHeader>

    <FormAlert :message="error" />

    <section class="card overflow-hidden">
      <div class="card-header">
        <h2 class="card-title">Contratos</h2>
        <select v-model="status" aria-label="Situação" class="field field-sm w-full sm:w-52">
          <option value="">Todas as situações</option>
          <option v-for="option in options?.contract_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <EmptyState
        v-if="page && page.data.length === 0"
        icon="file"
        title="Nenhum contrato."
        description="Eles são criados quando o cliente aceita um orçamento."
      />
      <div v-else class="overflow-x-auto">
        <table class="table-clean min-w-[960px]" data-test="contracts">
          <thead>
            <tr>
              <th>Contrato</th>
              <th>Cliente</th>
              <th>Aceito por</th>
              <th class="text-right">Valor</th>
              <th>Cobrança recorrente</th>
              <th>Situação</th>
              <th><span class="sr-only">Ações</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="contract in page?.data ?? []" :key="contract.id">
              <td>
                <p class="font-medium text-fg">{{ contract.title }}</p>
                <p class="text-xs text-fg-muted">
                  {{ contract.number }} · orçamento
                  <RouterLink v-if="contract.quote" :to="{ name: 'quote-edit', params: { id: contract.quote.id } }" class="link">{{ contract.quote.number }}</RouterLink>
                  <template v-if="contract.project">
                    · <RouterLink :to="{ name: 'project-board', params: { id: contract.project.id } }" class="link">projeto</RouterLink>
                  </template>
                </p>
              </td>
              <td class="text-fg-soft">{{ contract.client?.name }}</td>
              <td>
                <p class="text-fg-soft">{{ contract.signer_name }}</p>
                <p class="text-xs text-fg-muted">{{ new Date(contract.signed_at).toLocaleDateString('pt-BR') }}</p>
              </td>
              <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ formatMoney(contract.total, company.current?.currency) }}</td>
              <td data-test="recurrence">
                <template v-if="contract.recurrence">
                  <p class="flex items-center gap-1.5 text-fg-soft"><AppIcon name="repeat" :size="14" class="text-brand-400" />{{ contract.recurrence.frequency.label }} · {{ formatMoney(contract.recurrence.amount, company.current?.currency) }}</p>
                  <p class="text-xs text-fg-muted">
                    {{ contract.recurrence.next_billing_on ? `próxima em ${date(contract.recurrence.next_billing_on)}` : 'encerrada' }}
                  </p>
                </template>
                <span v-else class="text-fg-subtle">—</span>
              </td>
              <td><StatusBadge :tone="statusTone('contract', contract.status.value)">{{ contract.status.label }}</StatusBadge></td>
              <td class="text-right whitespace-nowrap">
                <div class="flex justify-end gap-1">
                  <button v-if="canBill && contract.status.value === 'active'" type="button" class="btn-ghost btn-sm" @click="editing = contract">Recorrência</button>
                  <template v-if="canManage && contract.status.value === 'active'">
                    <button type="button" class="btn-ghost btn-sm !text-emerald-400" @click="close(contract, 'completed')">Concluir</button>
                    <button type="button" class="btn-ghost btn-sm !text-red-400" @click="close(contract, 'cancelled')">Cancelar</button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <UiPagination v-if="page && page.meta.last_page > 1" :current="page.meta.current_page" :last="page.meta.last_page" @go="goTo" />
    </section>

    <RecurrenceModal v-if="editing" :contract="editing" @close="editing = null" @saved="onRecurrenceSaved" />
  </AppLayout>
</template>
