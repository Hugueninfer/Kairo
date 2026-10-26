<script setup lang="ts">
import { onMounted, reactive, ref, toRef } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import { useProjectPicker } from '@/composables/useProjectPicker'
import { formatMinutes, isoDate, parseDuration } from '@/composables/time'
import { timesheetApi } from '@/services/timesheet'
import type { TimeEntry } from '@/types/api'

const props = defineProps<{ entry?: TimeEntry | null; date?: string }>()
const emit = defineEmits<{ close: []; saved: [entry: TimeEntry] }>()

function localTime(iso: string): { date: string; time: string } {
  const value = new Date(iso)
  return { date: isoDate(value), time: `${String(value.getHours()).padStart(2, '0')}:${String(value.getMinutes()).padStart(2, '0')}` }
}

const started = props.entry ? localTime(props.entry.started_at) : null
const form = reactive({
  project_id: props.entry?.project?.id ?? '',
  task_id: props.entry?.task?.id ?? '',
  date: started?.date ?? props.date ?? isoDate(new Date()),
  start: started?.time ?? '',
  duration: props.entry ? formatMinutes(props.entry.duration_minutes) : '',
  description: props.entry?.description ?? '',
})
const durationError = ref<string | null>(null)

const { projects, tasks, loadProjects } = useProjectPicker(toRef(form, 'project_id'))
const save = useApiForm()

async function submit() {
  const minutes = parseDuration(form.duration)
  durationError.value = minutes === null ? 'Informe a duração, como 1:30 ou 1,5.' : null
  if (minutes === null) return

  const payload = {
    project_id: form.project_id,
    task_id: form.task_id || null,
    date: form.date,
    start: form.start || null,
    minutes,
    description: form.description || null,
  }
  const saved = await save.submit(() => (props.entry ? timesheetApi.update(props.entry.id, payload) : timesheetApi.create(payload)))
  if (saved) emit('saved', saved)
}

onMounted(loadProjects)
</script>

<template>
  <BaseModal :title="entry ? 'Editar horas' : 'Lançar horas'" @close="emit('close')">
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
      <div>
        <label for="entry-project" class="mb-1 block text-sm font-medium text-slate-700">Projeto</label>
        <select id="entry-project" v-model="form.project_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="form.task_id = ''">
          <option value="" disabled>Escolha um projeto</option>
          <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
        </select>
        <p v-if="save.fieldErrors.value.project_id" class="mt-1 text-xs text-red-600">{{ save.fieldErrors.value.project_id }}</p>
      </div>
      <div>
        <label for="entry-task" class="mb-1 block text-sm font-medium text-slate-700">Tarefa (opcional)</label>
        <select id="entry-task" v-model="form.task_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option value="">Sem tarefa</option>
          <option v-for="task in tasks" :key="task.id" :value="task.id">{{ task.title }}</option>
        </select>
      </div>
      <div class="grid gap-4 sm:grid-cols-3">
        <BaseInput id="entry-date" v-model="form.date" label="Dia" type="date" :error="save.fieldErrors.value.date" />
        <BaseInput id="entry-start" v-model="form.start" label="Início (opcional)" type="time" :error="save.fieldErrors.value.start" />
        <BaseInput id="entry-duration" v-model="form.duration" label="Duração" :error="durationError ?? save.fieldErrors.value.minutes" />
      </div>
      <BaseInput id="entry-description" v-model="form.description" label="O que foi feito" :error="save.fieldErrors.value.description" />
      <BaseButton :loading="save.submitting.value">Salvar</BaseButton>
    </form>
  </BaseModal>
</template>
