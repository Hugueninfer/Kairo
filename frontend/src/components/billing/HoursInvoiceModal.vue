<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { addDays, isoDate } from '@/composables/time'
import { useApiForm } from '@/composables/useApiForm'
import { invoicesApi } from '@/services/invoices'
import { projectsApi } from '@/services/projects'
import type { Invoice, Project } from '@/types/api'

const emit = defineEmits<{ close: []; saved: [invoice: Invoice] }>()
const save = useApiForm()
const projects = ref<Project[]>([])

// Padrão: o mês passado inteiro, com vencimento em 10 dias.
const today = new Date()
const form = reactive({
  project_id: '',
  from: isoDate(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
  to: isoDate(new Date(today.getFullYear(), today.getMonth(), 0)),
  due_date: addDays(isoDate(today), 10),
})

async function submit() {
  const created = await save.submit(() => invoicesApi.fromHours(form))
  if (created) emit('saved', created)
}

onMounted(async () => {
  projects.value = (await projectsApi.list({ per_page: 100 }).catch(() => ({ data: [] as Project[] }))).data.filter((p) => p.client)
})
</script>

<template>
  <BaseModal title="Faturar horas aprovadas" @close="emit('close')">
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-slate-600">
        Junta as horas aprovadas e cobráveis do projeto no período, um item por tarefa. Horas já faturadas ficam de fora.
      </p>
      <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
      <div>
        <label for="hours-project" class="mb-1 block text-sm font-medium text-slate-700">Projeto</label>
        <select id="hours-project" v-model="form.project_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option value="" disabled>Escolha o projeto (precisa ter cliente)</option>
          <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }} · {{ project.client?.name }}</option>
        </select>
        <p v-if="save.fieldErrors.value.project_id" class="mt-1 text-xs text-red-600">{{ save.fieldErrors.value.project_id }}</p>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <BaseInput id="hours-from" v-model="form.from" label="De" type="date" :error="save.fieldErrors.value.from" />
        <BaseInput id="hours-to" v-model="form.to" label="Até" type="date" :error="save.fieldErrors.value.to" />
      </div>
      <BaseInput id="hours-due" v-model="form.due_date" label="Vencimento" type="date" :error="save.fieldErrors.value.due_date" />
      <BaseButton :loading="save.submitting.value">Gerar fatura</BaseButton>
    </form>
  </BaseModal>
</template>
