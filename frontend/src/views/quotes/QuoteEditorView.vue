<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { addDays, isoDate } from '@/composables/time'
import { formatMoney, quoteTotals } from '@/composables/money'
import { useApiForm } from '@/composables/useApiForm'
import { useOptions } from '@/composables/useOptions'
import AppLayout from '@/layouts/AppLayout.vue'
import { clientsApi } from '@/services/clients'
import { toApiError } from '@/services/http'
import { quotesApi, type QuoteItemPayload, type QuotePayload } from '@/services/quotes'
import { useCompanyStore } from '@/stores/company'
import type { Client, Quote } from '@/types/api'

const route = useRoute()
const router = useRouter()
const company = useCompanyStore()
const { options, load: loadOptions } = useOptions()
const save = useApiForm()

const quote = ref<Quote | null>(null)
const clients = ref<Client[]>([])
const loadError = ref<string | null>(null)
const actionError = ref<string | null>(null)
const notice = ref<string | null>(null)
const busy = ref(false)

const blankItem = (): QuoteItemPayload => ({ description: '', type: 'fixed', quantity: '1', unit_price: '' })
const form = reactive<QuotePayload & { discount: string; tax_rate: string }>({
  client_id: '',
  title: '',
  valid_until: addDays(isoDate(new Date()), 15),
  introduction: '',
  terms: '',
  discount: '0',
  tax_rate: '0',
  items: [blankItem()],
})

const currency = computed(() => company.current?.currency ?? 'BRL')
const canManage = computed(() => company.can('quotes.manage'))
const readOnly = computed(() => !canManage.value || (quote.value?.status.is_decided ?? false))
const totals = computed(() => quoteTotals(form.items, form.discount, form.tax_rate))
const money = (value: string) => formatMoney(value, currency.value)
const date = (value: string) => new Date(value.length === 10 ? `${value}T12:00:00` : value).toLocaleDateString('pt-BR')

function fill(loaded: Quote) {
  quote.value = loaded
  Object.assign(form, {
    client_id: loaded.client?.id ?? '',
    title: loaded.title,
    valid_until: loaded.valid_until,
    introduction: loaded.introduction ?? '',
    terms: loaded.terms ?? '',
    discount: loaded.discount,
    tax_rate: loaded.tax_rate,
    items: (loaded.items ?? []).map((item) => ({
      description: item.description,
      type: item.type.value,
      quantity: item.quantity,
      unit_price: item.unit_price,
    })),
  })
}

function itemError(index: number, field: string): string | undefined {
  return save.fieldErrors.value[`items.${index}.${field}`]
}

function removeItem(index: number) {
  form.items.splice(index, 1)
  if (form.items.length === 0) form.items.push(blankItem())
}

/** Salva e devolve o orçamento salvo (ou undefined se a API recusar). */
async function persist(): Promise<Quote | undefined> {
  notice.value = null
  const payload: QuotePayload = {
    ...form,
    introduction: form.introduction || null,
    terms: form.terms || null,
    items: form.items.map((item) => ({ ...item, quantity: item.quantity.replace(',', '.'), unit_price: item.unit_price.replace(',', '.') })),
  }
  const saved = await save.submit(() => (quote.value ? quotesApi.update(quote.value.id, payload) : quotesApi.create(payload)))
  if (!saved) return undefined

  const wasNew = !quote.value
  fill(saved)
  if (wasNew) await router.replace({ name: 'quote-edit', params: { id: saved.id } })
  return saved
}

async function onSave() {
  if (await persist()) notice.value = 'Orçamento salvo.'
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

const sendToClient = () =>
  run(async () => {
    const saved = readOnly.value ? quote.value : await persist()
    if (!saved) return
    fill(await quotesApi.send(saved.id))
    notice.value = `Enviado para ${saved.client?.email}. O PDF é gerado e o e-mail sai em instantes.`
  })

const duplicate = () =>
  run(async () => {
    const copy = await quotesApi.duplicate(quote.value!.id)
    await router.push({ name: 'quote-edit', params: { id: copy.id } })
    fill(copy)
    notice.value = `Cópia criada: ${copy.number}.`
  })

const remove = () =>
  run(async () => {
    if (!window.confirm(`Apagar o orçamento ${quote.value!.number}?`)) return
    await quotesApi.remove(quote.value!.id)
    await router.push({ name: 'quotes' })
  })

onMounted(async () => {
  try {
    const [list] = await Promise.all([clientsApi.list({ per_page: 100 }), loadOptions()])
    clients.value = list.data
    if (typeof route.params.id === 'string') fill(await quotesApi.get(route.params.id))
  } catch (failure) {
    loadError.value = toApiError(failure).message
  }
})
</script>

<template>
  <AppLayout>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <RouterLink :to="{ name: 'quotes' }" class="text-sm text-brand-600 hover:underline">‹ Orçamentos</RouterLink>
        <h1 class="text-2xl font-semibold">
          {{ quote ? `Orçamento ${quote.number}` : 'Novo orçamento' }}
          <span v-if="quote" class="ml-2 rounded bg-slate-100 px-2 py-0.5 align-middle text-xs font-normal text-slate-600" data-test="quote-status">{{ quote.status.label }}</span>
        </h1>
      </div>
      <div v-if="quote" class="flex flex-wrap gap-3 text-sm">
        <a :href="quotesApi.pdfUrl(quote.id)" class="text-brand-600 hover:underline" data-test="pdf">Baixar PDF</a>
        <button v-if="canManage" type="button" class="text-brand-600 hover:underline" :disabled="busy" @click="duplicate">Duplicar</button>
        <button v-if="canManage && quote.status.value !== 'accepted'" type="button" class="text-red-600 hover:underline" :disabled="busy" @click="remove">Apagar</button>
      </div>
    </div>

    <FormAlert :message="loadError" />

    <!-- Situação do orçamento perante o cliente -->
    <div v-if="quote && quote.status.value !== 'draft'" class="rounded-xl px-4 py-3 text-sm ring-1" data-test="quote-banner"
      :class="{
        'bg-sky-50 text-sky-800 ring-sky-200': ['sent', 'viewed'].includes(quote.status.value),
        'bg-emerald-50 text-emerald-800 ring-emerald-200': quote.status.value === 'accepted',
        'bg-red-50 text-red-800 ring-red-200': quote.status.value === 'rejected',
        'bg-amber-50 text-amber-800 ring-amber-200': quote.status.value === 'expired',
      }"
    >
      <template v-if="quote.status.value === 'sent'">Enviado em {{ date(quote.sent_at!) }}. O cliente ainda não abriu o link.</template>
      <template v-else-if="quote.status.value === 'viewed'">O cliente abriu o orçamento em {{ date(quote.viewed_at!) }} e ainda não respondeu.</template>
      <template v-else-if="quote.status.value === 'accepted'">
        Aceito por <strong>{{ quote.signer_name }}</strong> em {{ date(quote.decided_at!) }}.
        <template v-if="quote.contract">Contrato {{ quote.contract.number }}. </template>
        <RouterLink v-if="quote.project" :to="{ name: 'project-board', params: { id: quote.project.id } }" class="font-semibold underline">Abrir o projeto</RouterLink>
      </template>
      <template v-else-if="quote.status.value === 'rejected'">
        Recusado em {{ date(quote.decided_at!) }}.<template v-if="quote.decision_note"> Comentário do cliente: “{{ quote.decision_note }}”</template>
      </template>
      <template v-else>A validade passou sem resposta. Ajuste a data e envie de novo.</template>
      <template v-if="!readOnly && ['sent', 'viewed', 'expired'].includes(quote.status.value)">
        <br /><span class="text-xs">Se você editar e salvar, ele volta para rascunho até ser enviado de novo.</span>
      </template>
    </div>

    <form class="space-y-6" novalidate @submit.prevent="onSave">
      <fieldset :disabled="readOnly" class="space-y-6">
        <section class="grid gap-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2">
          <div>
            <label for="quote-client" class="mb-1 block text-sm font-medium text-slate-700">Cliente</label>
            <select id="quote-client" v-model="form.client_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
              <option value="" disabled>Escolha o cliente</option>
              <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}{{ client.email ? '' : ' (sem e-mail)' }}</option>
            </select>
            <p v-if="save.fieldErrors.value.client_id" class="mt-1 text-xs text-red-600">{{ save.fieldErrors.value.client_id }}</p>
          </div>
          <BaseInput id="quote-valid" v-model="form.valid_until" label="Válido até" type="date" :error="save.fieldErrors.value.valid_until" />
          <div class="sm:col-span-2">
            <BaseInput id="quote-title" v-model="form.title" label="Título" :error="save.fieldErrors.value.title" />
          </div>
          <div class="sm:col-span-2">
            <label for="quote-intro" class="mb-1 block text-sm font-medium text-slate-700">Apresentação (opcional)</label>
            <textarea id="quote-intro" v-model="form.introduction" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
          </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
          <h2 class="mb-3 font-semibold">Itens</h2>
          <p v-if="save.fieldErrors.value.items" class="mb-2 text-xs text-red-600">{{ save.fieldErrors.value.items }}</p>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm" data-test="items">
              <thead>
                <tr class="text-left text-xs text-slate-500">
                  <th class="pb-2 font-medium">Descrição</th>
                  <th class="w-32 pb-2 font-medium">Tipo</th>
                  <th class="w-24 pb-2 font-medium">Qtd.</th>
                  <th class="w-32 pb-2 font-medium">Valor unitário</th>
                  <th class="w-28 pb-2 text-right font-medium">Total</th>
                  <th class="w-8" />
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in form.items" :key="index" class="align-top">
                  <td class="py-1 pr-2">
                    <input v-model="item.description" :aria-label="`Descrição do item ${index + 1}`" class="w-full rounded-lg border px-2 py-1.5" :class="itemError(index, 'description') ? 'border-red-400' : 'border-slate-300'" />
                    <p v-if="itemError(index, 'description')" class="text-xs text-red-600">{{ itemError(index, 'description') }}</p>
                  </td>
                  <td class="py-1 pr-2">
                    <select v-model="item.type" :aria-label="`Tipo do item ${index + 1}`" class="w-full rounded-lg border border-slate-300 px-2 py-1.5">
                      <option v-for="option in options?.quote_item_types ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                  </td>
                  <td class="py-1 pr-2">
                    <input v-model="item.quantity" inputmode="decimal" :aria-label="`Quantidade do item ${index + 1}`" class="w-full rounded-lg border px-2 py-1.5" :class="itemError(index, 'quantity') ? 'border-red-400' : 'border-slate-300'" />
                    <span class="text-xs text-slate-400">{{ item.type === 'hourly' ? 'horas' : 'unidades' }}</span>
                  </td>
                  <td class="py-1 pr-2">
                    <input v-model="item.unit_price" inputmode="decimal" placeholder="0,00" :aria-label="`Valor unitário do item ${index + 1}`" class="w-full rounded-lg border px-2 py-1.5" :class="itemError(index, 'unit_price') ? 'border-red-400' : 'border-slate-300'" />
                  </td>
                  <td class="py-2.5 text-right tabular-nums">{{ money(totals.lines[index] ?? '0') }}</td>
                  <td class="py-1 text-right">
                    <button v-if="!readOnly" type="button" class="px-1 text-slate-400 hover:text-red-600" :aria-label="`Remover item ${index + 1}`" @click="removeItem(index)">✕</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <button v-if="!readOnly" type="button" class="mt-2 text-sm font-medium text-brand-600 hover:underline" @click="form.items.push(blankItem())">+ Adicionar item</button>

          <div class="mt-6 ml-auto w-full max-w-sm space-y-2 text-sm" data-test="totals">
            <div class="flex justify-between"><span class="text-slate-600">Subtotal</span><span class="tabular-nums">{{ money(totals.subtotal) }}</span></div>
            <div class="flex items-center justify-between gap-3">
              <label for="quote-discount" class="text-slate-600">Desconto (R$)</label>
              <input id="quote-discount" v-model="form.discount" inputmode="decimal" class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-right" />
            </div>
            <p v-if="totals.discountTooHigh" class="text-right text-xs text-red-600">O desconto é maior que o subtotal.</p>
            <div class="flex items-center justify-between gap-3">
              <label for="quote-tax" class="text-slate-600">Impostos (%)</label>
              <input id="quote-tax" v-model="form.tax_rate" inputmode="decimal" class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-right" />
            </div>
            <div class="flex justify-between text-slate-600"><span>Valor dos impostos</span><span class="tabular-nums">{{ money(totals.tax) }}</span></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold">
              <span>Total</span><span class="tabular-nums" data-test="total">{{ money(totals.total) }}</span>
            </div>
          </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
          <label for="quote-terms" class="mb-1 block font-semibold">Condições (opcional)</label>
          <p class="mb-2 text-xs text-slate-500">Forma de pagamento, prazos, o que não está incluso. Vão para o PDF e para o contrato.</p>
          <textarea id="quote-terms" v-model="form.terms" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        </section>
      </fieldset>

      <div class="space-y-2">
        <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
        <FormAlert :message="actionError" />
        <FormAlert :message="notice" variant="success" />
      </div>

      <div v-if="!readOnly" class="flex flex-wrap items-center gap-3">
        <div class="w-40">
          <BaseButton :loading="save.submitting.value">Salvar rascunho</BaseButton>
        </div>
        <button
          type="button"
          class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
          :disabled="busy || save.submitting.value"
          data-test="send"
          @click="sendToClient"
        >
          {{ quote && ['sent', 'viewed'].includes(quote.status.value) ? 'Salvar e reenviar ao cliente' : 'Salvar e enviar ao cliente' }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>
