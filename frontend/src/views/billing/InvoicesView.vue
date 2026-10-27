<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import BillingSettingsModal from '@/components/billing/BillingSettingsModal.vue'
import ContractInvoiceModal from '@/components/billing/ContractInvoiceModal.vue'
import HoursInvoiceModal from '@/components/billing/HoursInvoiceModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { formatMoney } from '@/composables/money'
import { useOptions } from '@/composables/useOptions'
import AppLayout from '@/layouts/AppLayout.vue'
import { toApiError } from '@/services/http'
import { invoicesApi } from '@/services/invoices'
import { useCompanyStore } from '@/stores/company'
import type { Invoice, InvoiceSummary, Paginated } from '@/types/api'

const router = useRouter()
const company = useCompanyStore()
const canManage = computed(() => company.can('invoices.manage'))
const { options, load: loadOptions } = useOptions()

const page = ref<(Paginated<Invoice> & { summary: InvoiceSummary }) | null>(null)
const status = ref('')
const search = ref('')
const error = ref<string | null>(null)
const modal = ref<'hours' | 'contract' | 'reminders' | null>(null)

const money = (value: string) => formatMoney(value, company.current?.currency)
const date = (value: string) => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR')
const badge: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  open: 'bg-sky-50 text-sky-700',
  partially_paid: 'bg-indigo-50 text-indigo-700',
  overdue: 'bg-red-50 text-red-700',
  paid: 'bg-emerald-50 text-emerald-700',
  cancelled: 'bg-slate-100 text-slate-400 line-through',
}

async function load() {
  error.value = null
  try {
    page.value = await invoicesApi.list({ status: status.value || undefined, search: search.value || undefined })
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

async function onCreated(invoice: Invoice) {
  modal.value = null
  await router.push({ name: 'invoice', params: { id: invoice.id } })
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
      <h1 class="text-2xl font-semibold">Faturas</h1>
      <div class="flex flex-wrap gap-2 text-sm">
        <button type="button" class="rounded-lg px-3 py-2 font-medium text-brand-600 hover:bg-white" data-test="reminder-settings" @click="modal = 'reminders'">Lembretes</button>
        <template v-if="canManage">
          <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-medium hover:bg-slate-50" @click="modal = 'hours'">Faturar horas</button>
          <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-medium hover:bg-slate-50" @click="modal = 'contract'">Faturar contrato</button>
          <RouterLink :to="{ name: 'invoice-new' }" class="rounded-lg bg-brand-600 px-4 py-2 font-semibold text-white hover:bg-brand-700">Nova fatura</RouterLink>
        </template>
      </div>
    </div>

    <div v-if="page" class="grid gap-4 sm:grid-cols-3" data-test="summary">
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">A receber</p>
        <p class="text-2xl font-semibold tabular-nums">{{ money(page.summary.receivable) }}</p>
      </div>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Vencido</p>
        <p class="text-2xl font-semibold tabular-nums" :class="Number(page.summary.overdue) > 0 ? 'text-red-600' : ''">{{ money(page.summary.overdue) }}</p>
      </div>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Recebido no mês</p>
        <p class="text-2xl font-semibold tabular-nums text-emerald-700">{{ money(page.summary.received_this_month) }}</p>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-sm">
      <input v-model="search" type="search" placeholder="Buscar pelo número" aria-label="Buscar" class="w-56 rounded-lg border border-slate-300 px-3 py-1.5" />
      <select v-model="status" aria-label="Situação" class="rounded-lg border border-slate-300 px-2 py-1.5">
        <option value="">Todas as situações</option>
        <option v-for="option in options?.invoice_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
    </div>

    <FormAlert :message="error" />

    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <p v-if="page && page.data.length === 0" class="p-6 text-sm text-slate-500">Nenhuma fatura por aqui.</p>
      <ul class="divide-y divide-slate-100" data-test="invoices">
        <li v-for="invoice in page?.data ?? []" :key="invoice.id">
          <RouterLink :to="{ name: 'invoice', params: { id: invoice.id } }" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50">
            <span class="w-28 font-mono text-xs text-slate-500">{{ invoice.number }}</span>
            <div class="min-w-48 flex-1">
              <p class="font-medium">{{ invoice.client?.name }}</p>
              <p class="text-xs text-slate-500">{{ invoice.project?.name ?? 'Sem projeto' }}</p>
            </div>
            <span class="text-xs text-slate-500">vence {{ date(invoice.due_date) }}</span>
            <span class="w-28 text-right font-semibold tabular-nums">{{ money(invoice.total) }}</span>
            <span class="w-28 rounded px-2 py-0.5 text-center text-xs" :class="badge[invoice.status.value]">{{ invoice.status.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>

    <HoursInvoiceModal v-if="modal === 'hours'" @close="modal = null" @saved="onCreated" />
    <ContractInvoiceModal v-if="modal === 'contract'" @close="modal = null" @saved="onCreated" />
    <BillingSettingsModal v-if="modal === 'reminders'" @close="modal = null" />
  </AppLayout>
</template>
