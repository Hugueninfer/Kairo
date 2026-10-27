<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import RecurrenceModal from '@/components/billing/RecurrenceModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import QuotesTabs from '@/components/quotes/QuotesTabs.vue'
import { formatMoney } from '@/composables/money'
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
    page.value = await contractsApi.list({ status: status.value || undefined })
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

watch(status, load)
onMounted(() => Promise.all([load(), loadOptions()]))
</script>

<template>
  <AppLayout>
    <h1 class="text-2xl font-semibold">Orçamentos</h1>
    <QuotesTabs />

    <div class="flex items-center gap-3 text-sm">
      <select v-model="status" aria-label="Situação" class="rounded-lg border border-slate-300 px-2 py-1.5">
        <option value="">Todas as situações</option>
        <option v-for="option in options?.contract_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
    </div>

    <FormAlert :message="error" />

    <section class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <p v-if="page && page.data.length === 0" class="p-6 text-sm text-slate-500">
        Nenhum contrato. Eles são criados quando o cliente aceita um orçamento.
      </p>
      <table v-else class="w-full min-w-[900px] text-sm" data-test="contracts">
        <thead>
          <tr class="border-b border-slate-200 text-left text-slate-500">
            <th class="px-5 py-2 font-medium">Contrato</th>
            <th class="py-2 font-medium">Cliente</th>
            <th class="py-2 font-medium">Aceito por</th>
            <th class="py-2 text-right font-medium">Valor</th>
            <th class="py-2 pl-6 font-medium">Cobrança recorrente</th>
            <th class="py-2 text-center font-medium">Situação</th>
            <th class="px-5 py-2" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="contract in page?.data ?? []" :key="contract.id" class="border-b border-slate-100">
            <td class="px-5 py-3">
              <p class="font-medium">{{ contract.title }}</p>
              <p class="text-xs text-slate-500">
                {{ contract.number }} · orçamento
                <RouterLink v-if="contract.quote" :to="{ name: 'quote-edit', params: { id: contract.quote.id } }" class="text-brand-600 hover:underline">{{ contract.quote.number }}</RouterLink>
                <template v-if="contract.project">
                  · <RouterLink :to="{ name: 'project-board', params: { id: contract.project.id } }" class="text-brand-600 hover:underline">projeto</RouterLink>
                </template>
              </p>
            </td>
            <td class="py-3">{{ contract.client?.name }}</td>
            <td class="py-3">
              {{ contract.signer_name }}
              <p class="text-xs text-slate-500">{{ new Date(contract.signed_at).toLocaleDateString('pt-BR') }}</p>
            </td>
            <td class="py-3 text-right font-semibold tabular-nums">{{ formatMoney(contract.total, company.current?.currency) }}</td>
            <td class="py-3 pl-6" data-test="recurrence">
              <template v-if="contract.recurrence">
                <p>{{ contract.recurrence.frequency.label }} · {{ formatMoney(contract.recurrence.amount, company.current?.currency) }}</p>
                <p class="text-xs text-slate-500">
                  {{ contract.recurrence.next_billing_on ? `próxima em ${date(contract.recurrence.next_billing_on)}` : 'encerrada' }}
                </p>
              </template>
              <span v-else class="text-slate-400">—</span>
            </td>
            <td class="py-3 text-center">
              <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ contract.status.label }}</span>
            </td>
            <td class="px-5 py-3 text-right whitespace-nowrap">
              <button v-if="canBill && contract.status.value === 'active'" type="button" class="mr-3 text-brand-600 hover:underline" @click="editing = contract">
                Recorrência
              </button>
              <template v-if="canManage && contract.status.value === 'active'">
                <button type="button" class="text-emerald-700 hover:underline" @click="close(contract, 'completed')">Concluir</button>
                <button type="button" class="ml-3 text-red-600 hover:underline" @click="close(contract, 'cancelled')">Cancelar</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <RecurrenceModal v-if="editing" :contract="editing" @close="editing = null" @saved="onRecurrenceSaved" />
  </AppLayout>
</template>
