<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import { useOptions } from '@/composables/useOptions'
import { clientsApi } from '@/services/clients'
import { projectsApi } from '@/services/projects'
import type { BillingType, Client, Project, ProjectStatus } from '@/types/api'

const props = defineProps<{ project?: Project | null }>()
const emit = defineEmits<{ close: []; saved: [project: Project] }>()

const { options, load: loadOptions } = useOptions()
const clients = ref<Client[]>([])
const save = useApiForm()

const form = reactive({
  name: props.project?.name ?? '',
  client_id: props.project?.client?.id ?? '',
  description: props.project?.description ?? '',
  status: (props.project?.status.value ?? 'active') as ProjectStatus,
  billing_type: (props.project?.billing_type.value ?? 'hourly') as BillingType,
  hourly_rate: props.project?.hourly_rate ?? '',
  budget: props.project?.budget ?? '',
  starts_on: props.project?.starts_on ?? '',
  due_on: props.project?.due_on ?? '',
})

async function submit() {
  const payload = {
    ...form,
    client_id: form.client_id || null,
    description: form.description || null,
    hourly_rate: form.hourly_rate || null,
    budget: form.budget || null,
    starts_on: form.starts_on || null,
    due_on: form.due_on || null,
  }
  const saved = await save.submit(() => (props.project ? projectsApi.update(props.project.id, payload) : projectsApi.create(payload)))
  if (saved) emit('saved', saved)
}

onMounted(async () => {
  await loadOptions()
  // Lista curta para o select; a busca completa fica na tela de clientes.
  // Sem permissão de ver clientes, o select fica só com "Sem cliente".
  clients.value = await clientsApi
    .list({ per_page: 100 })
    .then((page) => page.data)
    .catch(() => [])
})
</script>

<template>
  <BaseModal :title="project ? 'Editar projeto' : 'Novo projeto'" @close="emit('close')">
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
      <BaseInput id="project-name" v-model="form.name" label="Nome" :error="save.fieldErrors.value.name" />

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label for="project-client" class="field-label">Cliente</label>
          <select id="project-client" v-model="form.client_id" class="field w-full">
            <option value="">Sem cliente</option>
            <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option>
          </select>
          <p v-if="save.fieldErrors.value.client_id" class="mt-1 text-xs text-red-400">{{ save.fieldErrors.value.client_id }}</p>
        </div>
        <div>
          <label for="project-status" class="field-label">Situação</label>
          <select id="project-status" v-model="form.status" class="field w-full">
            <option v-for="option in options?.project_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <div>
          <label for="project-billing" class="field-label">Cobrança</label>
          <select id="project-billing" v-model="form.billing_type" class="field w-full">
            <option v-for="option in options?.billing_types ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
        <BaseInput
          v-if="form.billing_type === 'hourly'"
          id="project-rate"
          v-model="form.hourly_rate"
          label="Valor/hora"
          type="number"
          :error="save.fieldErrors.value.hourly_rate"
        />
        <BaseInput
          v-if="form.billing_type === 'fixed'"
          id="project-budget"
          v-model="form.budget"
          label="Valor fechado"
          type="number"
          :error="save.fieldErrors.value.budget"
        />
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseInput id="project-start" v-model="form.starts_on" label="Início" type="date" :error="save.fieldErrors.value.starts_on" />
        <BaseInput id="project-due" v-model="form.due_on" label="Prazo" type="date" :error="save.fieldErrors.value.due_on" />
      </div>

      <div>
        <label for="project-description" class="field-label">Descrição</label>
        <textarea id="project-description" v-model="form.description" rows="3" class="field w-full" />
      </div>

      <BaseButton :loading="save.submitting.value">Salvar</BaseButton>
    </form>
  </BaseModal>
</template>
