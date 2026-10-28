<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import BillingSettingsModal from '@/components/billing/BillingSettingsModal.vue'
import ContractInvoiceModal from '@/components/billing/ContractInvoiceModal.vue'
import HoursInvoiceModal from '@/components/billing/HoursInvoiceModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import UiPagination from '@/components/ui/UiPagination.vue'
import { formatMoney } from '@/composables/money'
import { statusTone } from '@/composables/statusTone'
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
const currentPage = ref(1)
const error = ref<string | null>(null)
const modal = ref<'hours' | 'contract' | 'reminders' | null>(null)

const money = (value: string) => formatMoney(value, company.current?.currency)
const date = (value: string) => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR')
async function load() {
  error.value = null
  try {
    page.value = await invoicesApi.list({ status: status.value || undefined, search: search.value || undefined, page: currentPage.value })
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
    <PageHeader title="Faturas" subtitle="Cobranças, pagamentos e lembretes automáticos." :breadcrumbs="[{ label: 'Comercial' }, { label: 'Faturas' }]">
      <template #actions>
        <button type="button" class="btn-ghost" data-test="reminder-settings" @click="modal = 'reminders'"><AppIcon name="bell" :size="16" />Lembretes</button>
        <template v-if="canManage">
          <button type="button" class="btn-secondary" @click="modal = 'hours'"><AppIcon name="clock" :size="16" />Faturar horas</button>
          <button type="button" class="btn-secondary" @click="modal = 'contract'"><AppIcon name="repeat" :size="16" />Faturar contrato</button>
          <RouterLink :to="{ name: 'invoice-new' }" class="btn-primary"><AppIcon name="plus" :size="16" />Nova fatura</RouterLink>
        </template>
      </template>
    </PageHeader>

    <div v-if="page" class="grid gap-4 sm:grid-cols-3" data-test="summary">
      <StatCard icon="wallet" label="A receber" :value="money(page.summary.receivable)" :badge="{ text: 'Aberto', tone: 'blue' }" />
      <StatCard
        icon="alert"
        label="Vencido"
        :value="money(page.summary.overdue)"
        :value-class="Number(page.summary.overdue) > 0 ? 'text-red-400' : ''"
        :badge="Number(page.summary.overdue) > 0 ? { text: 'Atraso', tone: 'red', icon: 'trendingDown' } : { text: 'Em dia', tone: 'green', icon: 'check' }"
      />
      <StatCard icon="checkCircle" label="Recebido no mês" :value="money(page.summary.received_this_month)" :badge="{ text: 'Mês', tone: 'green', icon: 'trendingUp' }" />
    </div>

    <FormAlert :message="error" />

    <section class="card overflow-hidden">
      <div class="card-header">
        <div class="relative w-full sm:w-64">
          <AppIcon name="search" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-fg-subtle" />
          <input v-model="search" type="search" placeholder="Buscar pelo número" aria-label="Buscar" class="field field-sm w-full pl-9" />
        </div>
        <select v-model="status" aria-label="Situação" class="field field-sm w-full sm:w-52">
          <option value="">Todas as situações</option>
          <option v-for="option in options?.invoice_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <EmptyState v-if="page && page.data.length === 0" icon="receipt" title="Nenhuma fatura por aqui." />
      <div v-else class="overflow-x-auto">
        <table class="table-clean">
          <thead>
            <tr>
              <th class="hidden sm:table-cell">Número</th>
              <th>Cliente</th>
              <th class="hidden sm:table-cell">Vencimento</th>
              <th class="text-right">Total</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody data-test="invoices">
            <tr v-for="invoice in page?.data ?? []" :key="invoice.id" class="cursor-pointer" @click="$router.push({ name: 'invoice', params: { id: invoice.id } })">
              <td class="hidden whitespace-nowrap sm:table-cell">
                <RouterLink :to="{ name: 'invoice', params: { id: invoice.id } }" class="font-mono text-xs text-fg-soft hover:text-brand-300" @click.stop>{{ invoice.number }}</RouterLink>
              </td>
              <td class="sm:min-w-48">
                <p class="font-medium text-fg">{{ invoice.client?.name }}</p>
                <p class="text-xs text-fg-muted"><span class="font-mono sm:hidden">{{ invoice.number }} · </span>{{ invoice.project?.name ?? 'Sem projeto' }}</p>
              </td>
              <td class="hidden whitespace-nowrap text-fg-muted sm:table-cell">vence {{ date(invoice.due_date) }}</td>
              <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ money(invoice.total) }}</td>
              <td>
                <StatusBadge :tone="statusTone('invoice', invoice.status.value)" :class="invoice.status.value === 'cancelled' ? 'line-through' : ''">{{ invoice.status.label }}</StatusBadge>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <UiPagination v-if="page && page.meta.last_page > 1" :current="page.meta.current_page" :last="page.meta.last_page" @go="goTo" />
    </section>

    <HoursInvoiceModal v-if="modal === 'hours'" @close="modal = null" @saved="onCreated" />
    <ContractInvoiceModal v-if="modal === 'contract'" @close="modal = null" @saved="onCreated" />
    <BillingSettingsModal v-if="modal === 'reminders'" @close="modal = null" />
  </AppLayout>
</template>
