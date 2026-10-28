<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { statusTone } from '@/composables/statusTone'
import { formatMoney, fromCents, quoteTotals, toCents } from '@/composables/money'
import { addDays, isoDate } from '@/composables/time'
import { useApiForm } from '@/composables/useApiForm'
import { useOptions } from '@/composables/useOptions'
import AppLayout from '@/layouts/AppLayout.vue'
import { clientsApi } from '@/services/clients'
import { toApiError } from '@/services/http'
import { invoicesApi, type InvoiceItemPayload, type InvoicePayload } from '@/services/invoices'
import { projectsApi } from '@/services/projects'
import { useCompanyStore } from '@/stores/company'
import type { Client, Invoice, PaymentMethod, Project } from '@/types/api'

const route = useRoute()
const router = useRouter()
const company = useCompanyStore()
const { options, load: loadOptions } = useOptions()
const save = useApiForm()
const payment = useApiForm()

const invoice = ref<Invoice | null>(null)
const clients = ref<Client[]>([])
const projects = ref<Project[]>([])
const loadError = ref<string | null>(null)
const actionError = ref<string | null>(null)
const notice = ref<string | null>(null)
const busy = ref(false)
const copied = ref(false)

const blankItem = (): InvoiceItemPayload => ({ description: '', quantity: '1', unit_price: '' })
const form = reactive<InvoicePayload & { discount: string; notes: string; project_id: string }>({
  client_id: '',
  project_id: '',
  due_date: addDays(isoDate(new Date()), 10),
  notes: '',
  discount: '0',
  items: [blankItem()],
})
const paymentForm = reactive({ amount: '', method: 'pix' as PaymentMethod, paid_on: isoDate(new Date()) })

const canManage = computed(() => company.can('invoices.manage'))
const isDraft = computed(() => !invoice.value || invoice.value.status.value === 'draft')
const editable = computed(() => canManage.value && isDraft.value)
const totals = computed(() => quoteTotals(form.items, form.discount, '0'))
const balance = computed(() => (invoice.value ? fromCents(toCents(invoice.value.total) - toCents(invoice.value.amount_paid)) : '0'))
const money = (value: string) => formatMoney(value, company.current?.currency)
const date = (value: string) => new Date(value.length === 10 ? `${value}T12:00:00` : value).toLocaleDateString('pt-BR')

function fill(loaded: Invoice) {
  invoice.value = loaded
  paymentForm.amount = fromCents(toCents(loaded.total) - toCents(loaded.amount_paid))
  Object.assign(form, {
    client_id: loaded.client?.id ?? '',
    project_id: loaded.project?.id ?? '',
    due_date: loaded.due_date,
    notes: loaded.notes ?? '',
    discount: loaded.discount,
    items: (loaded.items ?? []).map((item) => ({ description: item.description, quantity: item.quantity, unit_price: item.unit_price })),
  })
}

async function persist(): Promise<Invoice | undefined> {
  notice.value = null
  const payload: InvoicePayload = {
    ...form,
    project_id: form.project_id || null,
    notes: form.notes || null,
    items: form.items.map((item) => ({ ...item, quantity: item.quantity.replace(',', '.'), unit_price: item.unit_price.replace(',', '.') })),
  }
  const saved = await save.submit(() => (invoice.value ? invoicesApi.update(invoice.value.id, payload) : invoicesApi.create(payload)))
  if (!saved) return undefined
  const wasNew = !invoice.value
  fill(saved)
  if (wasNew) await router.replace({ name: 'invoice', params: { id: saved.id } })
  return saved
}

async function onSave() {
  if (await persist()) notice.value = 'Rascunho salvo.'
}

async function run(action: () => Promise<void>) {
  busy.value = true
  actionError.value = null
  notice.value = null
  try {
    await action()
  } catch (failure) {
    actionError.value = toApiError(failure).message
  } finally {
    busy.value = false
  }
}

// Depois do envio, o link de pagamento é criado na fila: consulta até ele aparecer.
let poll: ReturnType<typeof setTimeout> | undefined
function waitForPaymentLink(attempt = 0) {
  clearTimeout(poll)
  if (!invoice.value || invoice.value.payment_url || attempt > 10) return
  poll = setTimeout(async () => {
    fill(await invoicesApi.get(invoice.value!.id))
    waitForPaymentLink(attempt + 1)
  }, 2000)
}

const send = () =>
  run(async () => {
    const saved = await persist()
    if (!saved) return
    fill(await invoicesApi.send(saved.id))
    notice.value = `Fatura fechada. O link de pagamento e o e-mail para ${saved.client?.email} saem em instantes.`
    waitForPaymentLink()
  })

const cancel = () =>
  run(async () => {
    if (!window.confirm(`Cancelar a fatura ${invoice.value!.number}? A cobrança no gateway também é cancelada.`)) return
    fill(await invoicesApi.cancel(invoice.value!.id))
    notice.value = 'Fatura cancelada. As horas dela podem ir para outra fatura.'
  })

const remove = () =>
  run(async () => {
    if (!window.confirm(`Apagar o rascunho ${invoice.value!.number}?`)) return
    await invoicesApi.remove(invoice.value!.id)
    await router.push({ name: 'invoices' })
  })

async function recordPayment() {
  const updated = await payment.submit(() => invoicesApi.recordPayment(invoice.value!.id, { ...paymentForm, amount: paymentForm.amount.replace(',', '.') }))
  if (updated) {
    fill(updated)
    notice.value = 'Pagamento registrado.'
  }
}

async function copyLink() {
  await navigator.clipboard?.writeText(invoice.value?.payment_url ?? '')
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function removeItem(index: number) {
  form.items.splice(index, 1)
  if (form.items.length === 0) form.items.push(blankItem())
}

onMounted(async () => {
  try {
    const [clientPage, projectPage] = await Promise.all([
      clientsApi.list({ per_page: 100 }),
      projectsApi.list({ per_page: 100 }).catch(() => ({ data: [] as Project[] })),
      loadOptions(),
    ])
    clients.value = clientPage.data
    projects.value = projectPage.data
    if (typeof route.params.id === 'string') {
      fill(await invoicesApi.get(route.params.id))
      waitForPaymentLink()
    }
  } catch (failure) {
    loadError.value = toApiError(failure).message
  }
})
onBeforeUnmount(() => clearTimeout(poll))
</script>

<template>
  <AppLayout>
    <PageHeader
      :title="invoice ? `Fatura ${invoice.number}` : 'Nova fatura'"
      :breadcrumbs="[{ label: 'Faturas', to: { name: 'invoices' } }, { label: invoice ? invoice.number : 'Nova' }]"
    >
      <template #title>
        <span class="flex flex-wrap items-center gap-3">
          {{ invoice ? `Fatura ${invoice.number}` : 'Nova fatura' }}
          <StatusBadge v-if="invoice" :tone="statusTone('invoice', invoice.status.value)" class="align-middle" data-test="invoice-status">{{ invoice.status.label }}</StatusBadge>
        </span>
      </template>
      <template v-if="invoice" #actions>
        <a :href="invoicesApi.pdfUrl(invoice.id)" class="btn-secondary"><AppIcon name="download" :size="16" />Baixar PDF</a>
        <button v-if="canManage && isDraft" type="button" class="btn-danger" :disabled="busy" @click="remove"><AppIcon name="trash" :size="16" />Apagar rascunho</button>
        <button v-if="canManage && invoice.status.awaits_payment && invoice.amount_paid === '0.00'" type="button" class="btn-danger" :disabled="busy" @click="cancel">Cancelar fatura</button>
      </template>
    </PageHeader>

    <FormAlert :message="loadError" />

    <!-- Fatura enviada: link de pagamento, saldo e pagamentos -->
    <section v-if="invoice && !isDraft" class="grid gap-4 lg:grid-cols-3">
      <div class="card p-5 lg:col-span-2" data-test="payment-box">
        <p class="text-sm text-fg-muted">
          {{ invoice.client?.name }} · vence {{ date(invoice.due_date) }}
          <template v-if="invoice.contract"> · contrato {{ invoice.contract.number }}<template v-if="invoice.billing_period_start"> (cobrança recorrente)</template></template>
        </p>
        <div class="mt-3 flex flex-wrap items-end gap-x-10 gap-y-4">
          <div>
            <p class="text-xs text-fg-muted">Total</p>
            <p class="text-2xl font-semibold tracking-tight tabular-nums">{{ money(invoice.total) }}</p>
          </div>
          <div>
            <p class="text-xs text-fg-muted">Recebido</p>
            <p class="text-2xl font-semibold tabular-nums text-emerald-400">{{ money(invoice.amount_paid) }}</p>
          </div>
          <div v-if="invoice.status.awaits_payment">
            <p class="text-xs text-fg-muted">Saldo</p>
            <p class="text-2xl font-semibold tabular-nums" :class="invoice.status.value === 'overdue' ? 'text-red-400' : ''">{{ money(balance) }}</p>
          </div>
        </div>
        <div v-if="invoice.status.awaits_payment" class="mt-5 rounded-lg border border-line bg-surface-2 p-3 text-sm">
          <template v-if="invoice.payment_url">
            <p class="mb-1.5 flex items-center gap-2 font-medium"><AppIcon name="link" :size="15" class="text-brand-400" />Link de pagamento (Pix, boleto ou cartão)</p>
            <div class="flex flex-wrap items-center gap-2">
              <a :href="invoice.payment_url" target="_blank" rel="noopener" class="link truncate" data-test="payment-url">{{ invoice.payment_url }}</a>
              <button type="button" class="btn-secondary btn-sm" @click="copyLink">{{ copied ? 'Copiado!' : 'Copiar' }}</button>
            </div>
          </template>
          <p v-else class="text-fg-muted">Gerando o link de pagamento no gateway…</p>
        </div>
        <p v-else-if="invoice.status.value === 'paid'" class="mt-4 font-medium text-emerald-400">Paga em {{ date(invoice.paid_at!) }}.</p>
        <p v-else-if="invoice.status.value === 'cancelled'" class="mt-4 text-fg-muted">Cancelada em {{ date(invoice.cancelled_at!) }}.</p>
      </div>

      <div class="card p-5">
        <h2 class="card-title mb-2">Pagamentos</h2>
        <p v-if="!invoice.payments?.length" class="text-sm text-fg-muted">Nenhum pagamento ainda.</p>
        <ul class="space-y-1 text-sm" data-test="payments">
          <li v-for="item in invoice.payments" :key="item.id" class="flex justify-between gap-2">
            <span>{{ date(item.paid_at) }} · {{ item.method.label }}<span v-if="item.via_gateway" class="text-xs text-fg-subtle"> (gateway)</span></span>
            <span class="font-medium tabular-nums">{{ money(item.amount) }}</span>
          </li>
        </ul>
        <form v-if="canManage && invoice.status.awaits_payment" class="mt-4 space-y-2 border-t border-line pt-3" novalidate @submit.prevent="recordPayment">
          <p class="text-xs font-medium text-fg-muted">Recebeu por fora do link? Registre aqui.</p>
          <div class="grid grid-cols-2 gap-2">
            <BaseInput id="payment-amount" v-model="paymentForm.amount" label="Valor" :error="payment.fieldErrors.value.amount" />
            <BaseInput id="payment-date" v-model="paymentForm.paid_on" label="Data" type="date" :error="payment.fieldErrors.value.paid_on" />
          </div>
          <select v-model="paymentForm.method" aria-label="Forma de pagamento" class="field field-sm w-full">
            <option v-for="option in options?.payment_methods ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <FormAlert v-if="Object.keys(payment.fieldErrors.value).length === 0" :message="payment.message.value" />
          <BaseButton :loading="payment.submitting.value">Registrar pagamento</BaseButton>
        </form>
        <div v-if="invoice.reminders?.length" class="mt-4 border-t border-line pt-3" data-test="reminders">
          <p class="mb-1 text-xs font-medium text-fg-muted">Lembretes enviados ao cliente</p>
          <ul class="space-y-0.5 text-xs text-fg-muted">
            <li v-for="reminder in invoice.reminders" :key="reminder.stage.value">{{ date(reminder.sent_at) }} · {{ reminder.stage.label }}</li>
          </ul>
        </div>
      </div>
    </section>

    <form class="space-y-6" novalidate @submit.prevent="onSave">
      <fieldset :disabled="!editable" class="space-y-6 [&_input:disabled]:border-transparent [&_input:disabled]:bg-transparent [&_input:disabled]:px-0 [&_textarea:disabled]:resize-none [&_textarea:disabled]:border-transparent [&_textarea:disabled]:bg-transparent [&_textarea:disabled]:px-0">
        <section v-if="isDraft" class="card grid gap-4 p-6 sm:grid-cols-3">
          <div>
            <label for="invoice-client" class="field-label">Cliente</label>
            <select id="invoice-client" v-model="form.client_id" class="field w-full">
              <option value="" disabled>Escolha o cliente</option>
              <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}{{ client.email ? '' : ' (sem e-mail)' }}</option>
            </select>
            <p v-if="save.fieldErrors.value.client_id" class="mt-1 text-xs text-red-400">{{ save.fieldErrors.value.client_id }}</p>
          </div>
          <div>
            <label for="invoice-project" class="field-label">Projeto (opcional)</label>
            <select id="invoice-project" v-model="form.project_id" class="field w-full">
              <option value="">Sem projeto</option>
              <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
            </select>
          </div>
          <BaseInput id="invoice-due" v-model="form.due_date" label="Vencimento" type="date" :error="save.fieldErrors.value.due_date" />
        </section>

        <section class="card p-6">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="card-title">Itens</h2>
            <span v-if="invoice?.time_entries_count" class="text-xs text-fg-muted">{{ invoice.time_entries_count }} apontamentos de horas nesta fatura</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm" data-test="items">
              <thead>
                <tr class="text-left text-xs text-fg-muted">
                  <th class="pb-2 font-medium">Descrição</th>
                  <th class="w-24 pb-2 font-medium">Qtd.</th>
                  <th class="w-32 pb-2 font-medium">Valor unitário</th>
                  <th class="w-28 pb-2 text-right font-medium">Total</th>
                  <th class="w-8" />
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in form.items" :key="index">
                  <td class="py-1 pr-2"><input v-model="item.description" :aria-label="`Descrição do item ${index + 1}`" class="field field-sm w-full" /></td>
                  <td class="py-1 pr-2"><input v-model="item.quantity" inputmode="decimal" :aria-label="`Quantidade do item ${index + 1}`" class="field field-sm w-full" /></td>
                  <td class="py-1 pr-2"><input v-model="item.unit_price" inputmode="decimal" placeholder="0,00" :aria-label="`Valor unitário do item ${index + 1}`" class="field field-sm w-full" /></td>
                  <td class="py-1 text-right tabular-nums">{{ money(totals.lines[index] ?? '0') }}</td>
                  <td class="py-1 text-right">
                    <button v-if="editable" type="button" class="rounded-md p-1 text-fg-subtle hover:bg-red-500/10 hover:text-red-300" :aria-label="`Remover item ${index + 1}`" @click="removeItem(index)"><AppIcon name="close" :size="15" /></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="Object.keys(save.fieldErrors.value).some((key) => key.startsWith('items'))" class="mt-1 text-xs text-red-400">Confira a descrição, a quantidade e o valor de cada item.</p>
          <button v-if="editable" type="button" class="btn-ghost btn-sm mt-2 !text-brand-400" @click="form.items.push(blankItem())"><AppIcon name="plus" :size="15" />Adicionar item</button>

          <div class="mt-6 ml-auto w-full max-w-sm space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-fg-muted">Subtotal</span><span class="tabular-nums">{{ money(totals.subtotal) }}</span></div>
            <div class="flex items-center justify-between gap-3">
              <label for="invoice-discount" class="text-fg-muted">Desconto (R$)</label>
              <input id="invoice-discount" v-model="form.discount" inputmode="decimal" class="field field-sm w-28 text-right" />
            </div>
            <p v-if="totals.discountTooHigh" class="text-right text-xs text-red-400">O desconto é maior que o subtotal.</p>
            <div class="flex justify-between border-t border-line pt-2 text-base font-semibold">
              <span>Total</span><span class="tabular-nums" data-test="total">{{ money(totals.total) }}</span>
            </div>
          </div>
        </section>

        <section class="card p-6">
          <label for="invoice-notes" class="card-title mb-3 block">Observações (opcional)</label>
          <textarea id="invoice-notes" v-model="form.notes" rows="3" class="field w-full" />
        </section>
      </fieldset>

      <div class="space-y-2">
        <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
        <FormAlert :message="actionError" />
        <FormAlert :message="notice" variant="success" />
      </div>

      <div v-if="editable" class="flex flex-wrap items-center gap-3">
        <BaseButton inline :loading="save.submitting.value">Salvar rascunho</BaseButton>
        <button type="button" class="btn-success" :disabled="busy || save.submitting.value" data-test="send" @click="send">
          <AppIcon name="send" :size="15" />Fechar e enviar ao cliente
        </button>
      </div>
    </form>
  </AppLayout>
</template>
