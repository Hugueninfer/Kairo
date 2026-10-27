<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import { useOptions } from '@/composables/useOptions'
import { toApiError } from '@/services/http'
import { invoicesApi } from '@/services/invoices'
import { useCompanyStore } from '@/stores/company'
import type { ReminderStage } from '@/types/api'

const emit = defineEmits<{ close: [] }>()

const company = useCompanyStore()
const canManage = computed(() => company.can('invoices.manage'))
const { options, load: loadOptions } = useOptions()
const save = useApiForm()
const loaded = ref(false)
const loadError = ref<string | null>(null)
const saved = ref<string | null>(null)
const form = reactive({ reminders_enabled: true, reminder_stages: [] as ReminderStage[], reminder_message: '' })

async function submit() {
  saved.value = null
  const result = await save.submit(() =>
    invoicesApi.updateBillingSettings({
      reminders_enabled: form.reminders_enabled,
      reminder_stages: form.reminder_stages,
      reminder_message: form.reminder_message.trim() || null,
    }),
  )
  if (result) saved.value = 'Régua de lembretes salva.'
}

onMounted(async () => {
  try {
    const [settings] = await Promise.all([invoicesApi.billingSettings(), loadOptions()])
    form.reminders_enabled = settings.reminders_enabled
    form.reminder_stages = [...settings.reminder_stages]
    form.reminder_message = settings.reminder_message ?? ''
    loaded.value = true
  } catch (failure) {
    loadError.value = toApiError(failure).message
  }
})
</script>

<template>
  <BaseModal title="Lembretes de cobrança" @close="emit('close')">
    <FormAlert :message="loadError" />
    <form v-if="loaded" class="space-y-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-slate-600">
        O Kairo manda um e-mail com o link de pagamento para o cliente de cada fatura em aberto, às 9h, nos dias que você marcar.
      </p>
      <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
      <FormAlert :message="saved" variant="success" />
      <fieldset :disabled="!canManage" class="space-y-4">
        <label class="flex items-center gap-2 text-sm font-medium">
          <input v-model="form.reminders_enabled" type="checkbox" class="rounded border-slate-300" data-test="reminders-enabled" />
          Enviar lembretes automáticos
        </label>
        <div :class="{ 'opacity-50': !form.reminders_enabled }" class="space-y-2 rounded-lg bg-slate-50 p-3" data-test="stages">
          <label v-for="option in options?.reminder_stages ?? []" :key="option.value" class="flex items-center gap-2 text-sm">
            <input v-model="form.reminder_stages" type="checkbox" :value="option.value" :disabled="!form.reminders_enabled" class="rounded border-slate-300" />
            {{ option.label }}
          </label>
        </div>
        <div>
          <label for="reminder-message" class="mb-1 block text-sm font-medium text-slate-700">Recado no e-mail (opcional)</label>
          <textarea
            id="reminder-message"
            v-model="form.reminder_message"
            rows="3"
            maxlength="1000"
            placeholder="Ex.: Qualquer dúvida, é só responder este e-mail."
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
          <p v-if="save.fieldErrors.value.reminder_message" class="mt-1 text-xs text-red-600">{{ save.fieldErrors.value.reminder_message }}</p>
        </div>
      </fieldset>
      <BaseButton v-if="canManage" :loading="save.submitting.value">Salvar</BaseButton>
    </form>
  </BaseModal>
</template>
