<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { formatMoney } from '@/composables/money'
import { addDays, isoDate } from '@/composables/time'
import { useApiForm } from '@/composables/useApiForm'
import { invoicesApi } from '@/services/invoices'
import { contractsApi } from '@/services/quotes'
import type { Contract, Invoice } from '@/types/api'

const emit = defineEmits<{ close: []; saved: [invoice: Invoice] }>()
const save = useApiForm()
const contracts = ref<Contract[]>([])
const form = reactive({ contract_id: '', percent: '100', due_date: addDays(isoDate(new Date()), 10) })

async function submit() {
  const created = await save.submit(() => invoicesApi.fromContract({ ...form, percent: form.percent.replace(',', '.') }))
  if (created) emit('saved', created)
}

onMounted(async () => {
  contracts.value = (await contractsApi.list({ status: 'active' }).catch(() => ({ data: [] as Contract[] }))).data
})
</script>

<template>
  <BaseModal title="Faturar contrato" @close="emit('close')">
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-slate-600">Cobre o contrato inteiro ou uma parcela, como 50% na entrada.</p>
      <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
      <div>
        <label for="contract-select" class="mb-1 block text-sm font-medium text-slate-700">Contrato</label>
        <select id="contract-select" v-model="form.contract_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option value="" disabled>Escolha o contrato vigente</option>
          <option v-for="contract in contracts" :key="contract.id" :value="contract.id">
            {{ contract.number }} · {{ contract.title }} ({{ formatMoney(contract.total) }})
          </option>
        </select>
        <p v-if="save.fieldErrors.value.contract_id" class="mt-1 text-xs text-red-600">{{ save.fieldErrors.value.contract_id }}</p>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <BaseInput id="contract-percent" v-model="form.percent" label="Percentual (%)" :error="save.fieldErrors.value.percent" />
        <BaseInput id="contract-due" v-model="form.due_date" label="Vencimento" type="date" :error="save.fieldErrors.value.due_date" />
      </div>
      <BaseButton :loading="save.submitting.value">Gerar fatura</BaseButton>
    </form>
  </BaseModal>
</template>
