<script setup lang="ts">
import { reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import { toApiError } from '@/services/http'
import { tasksApi } from '@/services/projects'
import { useTimerStore } from '@/stores/timer'
import type { Member, Options, Task, TaskPriority, TaskStatus } from '@/types/api'

const props = defineProps<{
  projectId: string
  task?: Task | null
  status?: TaskStatus
  members: Member[]
  options: Options | null
  canEdit: boolean
}>()
const emit = defineEmits<{ close: []; saved: [task: Task]; removed: [task: Task] }>()

const timer = useTimerStore()
const timerError = ref<string | null>(null)

async function startTimer() {
  if (!props.task) return
  timerError.value = null
  try {
    await timer.start({ project_id: props.projectId, task_id: props.task.id, description: null })
  } catch (failure) {
    timerError.value = toApiError(failure).message
  }
}

const save = useApiForm()
const form = reactive({
  title: props.task?.title ?? '',
  description: props.task?.description ?? '',
  status: (props.task?.status.value ?? props.status ?? 'todo') as TaskStatus,
  priority: (props.task?.priority.value ?? 'medium') as TaskPriority,
  assignee_id: props.task?.assignee?.id ?? null,
  estimate_hours: props.task?.estimate_minutes ? String(props.task.estimate_minutes / 60) : '',
  due_on: props.task?.due_on ?? '',
})

async function submit() {
  const hours = parseFloat(form.estimate_hours.replace(',', '.'))
  const payload = {
    title: form.title,
    description: form.description || null,
    status: form.status,
    priority: form.priority,
    assignee_id: form.assignee_id,
    estimate_minutes: Number.isFinite(hours) && hours > 0 ? Math.round(hours * 60) : null,
    due_on: form.due_on || null,
  }
  const saved = await save.submit(() =>
    props.task ? tasksApi.update(props.projectId, props.task.id, payload) : tasksApi.create(props.projectId, payload),
  )
  if (saved) emit('saved', saved)
}

async function remove() {
  if (!props.task || !window.confirm('Apagar esta tarefa?')) return
  const removed = await save.submit(() => tasksApi.remove(props.projectId, props.task!.id).then(() => true))
  if (removed) emit('removed', props.task)
}
</script>

<template>
  <BaseModal :title="task ? 'Tarefa' : 'Nova tarefa'" @close="emit('close')">
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
      <fieldset :disabled="!canEdit" class="space-y-4">
        <BaseInput id="task-title" v-model="form.title" label="Título" :error="save.fieldErrors.value.title" />
        <div>
          <label for="task-description" class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
          <textarea id="task-description" v-model="form.description" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="task-status" class="mb-1 block text-sm font-medium text-slate-700">Coluna</label>
            <select id="task-status" v-model="form.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
              <option v-for="option in options?.task_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div>
            <label for="task-priority" class="mb-1 block text-sm font-medium text-slate-700">Prioridade</label>
            <select id="task-priority" v-model="form.priority" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
              <option v-for="option in options?.task_priorities ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div>
            <label for="task-assignee" class="mb-1 block text-sm font-medium text-slate-700">Responsável</label>
            <select id="task-assignee" v-model="form.assignee_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
              <option :value="null">Ninguém</option>
              <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
            </select>
          </div>
          <BaseInput id="task-due" v-model="form.due_on" label="Prazo" type="date" :error="save.fieldErrors.value.due_on" />
          <BaseInput id="task-estimate" v-model="form.estimate_hours" label="Estimativa (horas)" type="number" :error="save.fieldErrors.value.estimate_minutes" />
        </div>
      </fieldset>
      <div v-if="canEdit" class="flex items-center gap-3">
        <div class="flex-1">
          <BaseButton :loading="save.submitting.value">Salvar</BaseButton>
        </div>
        <button v-if="task" type="button" class="text-sm text-red-600 hover:underline" @click="remove">Apagar</button>
      </div>
      <div v-if="task && task.status.value !== 'done'" class="flex items-center gap-3 border-t border-slate-100 pt-3 text-sm">
        <span v-if="timer.running?.task?.id === task.id" class="font-medium text-emerald-700">⏱ Timer rodando nesta tarefa</span>
        <button v-else type="button" class="font-semibold text-emerald-700 hover:underline" data-test="task-timer" @click="startTimer">
          ▶ Iniciar timer nesta tarefa
        </button>
        <FormAlert :message="timerError" />
      </div>
    </form>
  </BaseModal>
</template>
