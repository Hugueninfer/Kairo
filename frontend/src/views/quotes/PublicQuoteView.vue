<script setup lang="ts">
import { isAxiosError } from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import AppLogo from '@/components/ui/AppLogo.vue'
import { formatMoney, toCents } from '@/composables/money'
import { useApiForm } from '@/composables/useApiForm'
import { toApiError } from '@/services/http'
import { publicQuoteApi } from '@/services/quotes'
import type { PublicQuote } from '@/types/api'

// Página que o cliente abre pelo link do e-mail, sem login. A assinatura do link vai junto para a API.
const route = useRoute()
const id = String(route.params.id)
const signed = { expires: String(route.query.expires ?? ''), signature: String(route.query.signature ?? '') }

const quote = ref<PublicQuote | null>(null)
const loadError = ref<string | null>(null)
const mode = ref<'accept' | 'reject' | null>(null)
const signerName = ref('')
const note = ref('')
const decision = useApiForm()

const money = (value: string) => formatMoney(value, quote.value?.currency)
const date = (value: string) => new Date(value.length === 10 ? `${value}T12:00:00` : value).toLocaleDateString('pt-BR')
const quantity = (value: string) => Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 })
const hasDiscount = computed(() => quote.value !== null && toCents(quote.value.discount) > 0)
const hasTax = computed(() => quote.value !== null && toCents(quote.value.tax_rate) > 0)

async function decide() {
  if (!mode.value) return
  const answered = await decision.submit(() =>
    publicQuoteApi.decide(id, signed, mode.value === 'accept' ? { decision: 'accept', signer_name: signerName.value } : { decision: 'reject', note: note.value || undefined }),
  )
  if (answered) {
    quote.value = answered
    mode.value = null
  }
}

onMounted(async () => {
  try {
    quote.value = await publicQuoteApi.get(id, signed)
  } catch (failure) {
    // 403 = assinatura inválida ou link vencido.
    loadError.value = isAxiosError(failure) && failure.response?.status === 403
      ? 'Este link é inválido ou expirou. Peça um novo link a quem enviou o orçamento.'
      : toApiError(failure).message
  }
})
</script>

<template>
  <main class="mx-auto max-w-3xl space-y-6 px-4 py-10">
    <p class="flex items-center justify-center gap-2 text-sm text-fg-muted">Orçamento enviado pelo <AppLogo :height="16" /></p>

    <FormAlert :message="loadError" />

    <template v-if="quote">
      <header class="card p-6">
        <p class="text-sm text-fg-muted">{{ quote.company.name }} · Orçamento {{ quote.number }}</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ quote.title }}</h1>
        <p class="mt-1 text-sm text-fg-muted">Para {{ quote.client.name }} · válido até {{ date(quote.valid_until) }}</p>
        <p v-if="quote.introduction" class="mt-4 whitespace-pre-line text-sm">{{ quote.introduction }}</p>
      </header>

      <section class="card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="table-clean min-w-[520px]">
            <thead>
              <tr>
                <th>Descrição</th>
                <th class="text-right">Qtd.</th>
                <th class="text-right">Valor unitário</th>
                <th class="text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in quote.items" :key="index">
                <td class="text-fg">{{ item.description }}</td>
                <td class="text-right whitespace-nowrap text-fg-muted tabular-nums">{{ quantity(item.quantity) }} {{ item.type.unit }}</td>
                <td class="text-right whitespace-nowrap text-fg-muted tabular-nums">{{ money(item.unit_price) }}</td>
                <td class="text-right font-medium whitespace-nowrap tabular-nums">{{ money(item.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <dl class="ml-auto w-full max-w-xs space-y-1 border-t border-line p-5 text-sm sm:border-t-0">
          <div class="flex justify-between"><dt class="text-fg-muted">Subtotal</dt><dd class="tabular-nums">{{ money(quote.subtotal) }}</dd></div>
          <div v-if="hasDiscount" class="flex justify-between"><dt class="text-fg-muted">Desconto</dt><dd class="tabular-nums">− {{ money(quote.discount) }}</dd></div>
          <div v-if="hasTax" class="flex justify-between"><dt class="text-fg-muted">Impostos ({{ quantity(quote.tax_rate) }}%)</dt><dd class="tabular-nums">{{ money(quote.tax_amount) }}</dd></div>
          <div class="flex justify-between border-t border-line pt-2 text-base font-semibold"><dt>Total</dt><dd class="tabular-nums" data-test="public-total">{{ money(quote.total) }}</dd></div>
        </dl>
      </section>

      <section v-if="quote.terms" class="card p-6">
        <h2 class="card-title mb-2">Condições</h2>
        <p class="whitespace-pre-line text-sm">{{ quote.terms }}</p>
      </section>

      <section class="card p-6" data-test="decision">
        <template v-if="quote.status.value === 'accepted'">
          <p class="font-semibold text-emerald-400">Orçamento aceito por {{ quote.signer_name }} em {{ date(quote.decided_at!) }}.</p>
          <p class="mt-1 text-sm text-fg-muted">Obrigado! {{ quote.company.name }} já foi avisado e vai entrar em contato.</p>
        </template>
        <template v-else-if="quote.status.value === 'rejected'">
          <p class="font-semibold text-fg-soft">Você recusou este orçamento em {{ date(quote.decided_at!) }}.</p>
          <p class="mt-1 text-sm text-fg-muted">{{ quote.company.name }} já foi avisado.</p>
        </template>
        <template v-else-if="!quote.awaits_decision">
          <p class="font-semibold text-amber-400">A validade deste orçamento terminou.</p>
          <p class="mt-1 text-sm text-fg-muted">Se ainda tiver interesse, peça uma nova proposta a {{ quote.company.name }}.</p>
        </template>
        <template v-else>
          <div v-if="!mode" class="flex flex-wrap gap-3">
            <button type="button" class="btn-success px-5" @click="mode = 'accept'">Aceitar orçamento</button>
            <button type="button" class="btn-secondary px-5 font-semibold text-fg-soft" @click="mode = 'reject'">Recusar</button>
          </div>
          <form v-else class="space-y-4" novalidate @submit.prevent="decide">
            <template v-if="mode === 'accept'">
              <p class="text-sm text-fg-muted">
                Para aceitar, digite seu nome completo. Ele fica registrado como sua assinatura, junto com a data e o endereço de acesso.
              </p>
              <BaseInput id="signer-name" v-model="signerName" label="Nome completo" autocomplete="name" :error="decision.fieldErrors.value.signer_name" />
            </template>
            <template v-else>
              <label for="reject-note" class="field-label">Quer contar o motivo? (opcional)</label>
              <textarea id="reject-note" v-model="note" rows="3" class="field w-full" />
            </template>
            <FormAlert v-if="Object.keys(decision.fieldErrors.value).length === 0" :message="decision.message.value" />
            <div class="flex items-center gap-3">
              <BaseButton inline :loading="decision.submitting.value">{{ mode === 'accept' ? 'Confirmar aceite' : 'Confirmar recusa' }}</BaseButton>
              <button type="button" class="text-sm text-fg-muted hover:underline" @click="mode = null">Voltar</button>
            </div>
          </form>
        </template>
      </section>
    </template>
  </main>
</template>
