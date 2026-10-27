<script setup lang="ts">
import { onMounted, reactive } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { formatMoney } from '@/composables/money'
import { isoDate } from '@/composables/time'
import { useApiForm } from '@/composables/useApiForm'
import { useOptions } from '@/composables/useOptions'
import { contractsApi } from '@/services/quotes'
import type { Contract, RecurrenceFrequency } from '@/types/api'

const props = defineProps<{ contract: Contract }>()
const emit = defineEmits<{ close: []; saved: [contract: Contract] }>()

const { options, load: loadOptions } = useOptions()
const save = useApiForm()
const current = props.contract.recurrence
const form = reactive({
  frequency: (current?.frequency.value ?? 'monthly') as RecurrenceFrequency,
  amount: current?.amount ?? '',
  starts_on: current?.next_billing_on ?? isoDate(new Date()),
  ends_on: current?.ends_on ?? '',
  payment_term_days: String(current?.payment_term_days ?? 7),
  auto_send: current?.auto_send ?? true,
})

async function submit() {
  const saved = await save.submit(() =>
    contractsApi.recurrence(props.contract.id, {
      frequency: form.frequency,
      amount: form.amount.replace(',', '.'),
      starts_on: form.starts_on,
      ends_on: form.ends_on || null,
      payment_term_days: Number(form.payment_term_days || 0),
      auto_send: form.auto_send,
    }),
  )
  if (saved) emit('saved', saved)
}

async function turnOff() {
  if (!window.confirm(`Desligar a cobrança recorrente do contrato ${props.contract.number}? As faturas já geradas continuam valendo.`)) return
  const saved = await save.submit(() => contractsApi.recurrence(props.contract.id, { frequency: null }))
  if (saved) emit('saved', saved)
}

onMounted(loadOptions)
</script>

<template>
  <BaseModal title="Cobrança recorrente" @close="emit('close')">
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-slate-600">
        {{ contract.number }} · {{ contract.title }} ({{ formatMoney(contract.total) }}). No dia de cada cobrança o Kairo gera a fatura e, se você deixar
        marcado, já envia ao cliente com o link de pagamento.
      </p>
      <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label for="recurrence-frequency" class="mb-1 block text-sm font-medium text-slate-700">Frequência</label>
          <select id="recurrence-frequency" v-model="form.frequency" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option v-for="option in options?.recurrence_frequencies ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
        <BaseInput id="recurrence-amount" v-model="form.amount" label="Valor de cada cobrança" :error="save.fieldErrors.value.amount" />
        <BaseInput id="recurrence-start" v-model="form.starts_on" label="Primeira cobrança" type="date" :error="save.fieldErrors.value.starts_on" />
        <BaseInput id="recurrence-end" v-model="form.ends_on" label="Última cobrança (opcional)" type="date" :error="save.fieldErrors.value.ends_on" />
        <BaseInput
          id="recurrence-term"
          v-model="form.payment_term_days"
          label="Prazo para pagar (dias)"
          type="number"
          :error="save.fieldErrors.value.payment_term_days"
        />
      </div>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.auto_send" type="checkbox" class="rounded border-slate-300" data-test="auto-send" />
        Enviar ao cliente automaticamente
      </label>
      <p v-if="!form.auto_send" class="text-xs text-slate-500">A fatura fica em rascunho para você revisar e enviar.</p>
      <BaseButton :loading="save.submitting.value">{{ contract.recurrence ? 'Salvar' : 'Ligar cobrança recorrente' }}</BaseButton>
      <button v-if="contract.recurrence" type="button" class="w-full text-sm text-red-600 hover:underline" data-test="turn-off" @click="turnOff">
        Desligar cobrança recorrente
      </button>
    </form>
  </BaseModal>
</template>
