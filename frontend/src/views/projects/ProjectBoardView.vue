<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue'
import TaskModal from '@/components/projects/TaskModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { columnOf, moveTask } from '@/composables/board'
import { useOptions } from '@/composables/useOptions'
import AppLayout from '@/layouts/AppLayout.vue'
import { companiesApi } from '@/services/companies'
import { toApiError } from '@/services/http'
import { projectsApi, tasksApi } from '@/services/projects'
import { useCompanyStore } from '@/stores/company'
import type { Member, Project, Task, TaskStatus } from '@/types/api'

const props = defineProps<{ id: string }>()
const router = useRouter()
const company = useCompanyStore()
const canManageTasks = computed(() => company.can('tasks.manage'))
const canManageProject = computed(() => company.can('projects.manage'))
const { options, load: loadOptions } = useOptions()

const project = ref<Project | null>(null)
const tasks = ref<Task[]>([])
const members = ref<Member[]>([])
const error = ref<string | null>(null)

const columns = computed(() =>
  (options.value?.task_statuses ?? []).map((status) => ({ ...status, tasks: columnOf(tasks.value, status.value) })),
)

const modal = ref<{ task: Task | null; status?: TaskStatus } | null>(null)
const editingProject = ref(false)

const priorityClass: Record<string, string> = {
  low: 'bg-slate-100 text-slate-600',
  medium: 'bg-sky-50 text-sky-700',
  high: 'bg-amber-50 text-amber-700',
  urgent: 'bg-red-50 text-red-700',
}

async function load() {
  try {
    const [loadedProject, loadedTasks] = await Promise.all([projectsApi.get(props.id), tasksApi.board(props.id), loadOptions()])
    project.value = loadedProject
    tasks.value = loadedTasks
  } catch (failure) {
    error.value = toApiError(failure).message
  }
  // A lista de membros é só para escolher o responsável; sem permissão de ver a equipe, fica vazia.
  members.value = await companiesApi.members().catch(() => [])
}

// Arrastar e soltar (HTML5). A posição sai de onde o cartão foi solto em relação aos outros.
const dragging = ref<string | null>(null)
const dropTarget = ref<TaskStatus | null>(null)

function onDragStart(task: Task, event: DragEvent) {
  dragging.value = task.id
  event.dataTransfer?.setData('text/plain', task.id)
}

function dropIndex(event: DragEvent): number {
  const list = event.currentTarget as HTMLElement
  const cards = [...list.querySelectorAll<HTMLElement>('[data-card]')].filter((card) => card.dataset.card !== dragging.value)
  const index = cards.findIndex((card) => {
    const box = card.getBoundingClientRect()
    return event.clientY < box.top + box.height / 2
  })
  return index === -1 ? cards.length : index
}

async function onDrop(status: { value: TaskStatus; label: string }, event: DragEvent) {
  const taskId = dragging.value
  dragging.value = null
  dropTarget.value = null
  if (!taskId) return

  const position = dropIndex(event)
  const before = tasks.value
  tasks.value = moveTask(tasks.value, taskId, status, position)

  try {
    const saved = await tasksApi.move(props.id, taskId, status.value, position)
    tasks.value = tasks.value.map((task) => (task.id === saved.id ? { ...task, ...saved } : task))
  } catch (failure) {
    tasks.value = before
    error.value = toApiError(failure).message
  }
}

async function onSaved() {
  modal.value = null
  tasks.value = await tasksApi.board(props.id)
}

function onRemoved(task: Task) {
  modal.value = null
  tasks.value = tasks.value.filter((item) => item.id !== task.id)
}

async function onProjectSaved(saved: Project) {
  editingProject.value = false
  project.value = saved
}

async function removeProject() {
  if (!project.value || !window.confirm(`Apagar o projeto ${project.value.name} e as tarefas dele?`)) return
  try {
    await projectsApi.remove(project.value.id)
    await router.push({ name: 'projects' })
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

onMounted(load)
</script>

<template>
  <AppLayout wide>
    <div v-if="project" class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <RouterLink :to="{ name: 'projects' }" class="text-sm text-slate-500 hover:text-brand-600">← Projetos</RouterLink>
        <h1 class="text-2xl font-semibold">{{ project.name }}</h1>
        <p class="text-sm text-slate-500">
          {{ project.client?.name ?? 'Sem cliente' }} · {{ project.status.label }} · {{ project.billing_type.label }}
        </p>
      </div>
      <div v-if="canManageProject" class="flex gap-4 text-sm">
        <button type="button" class="text-brand-600 hover:underline" @click="editingProject = true">Editar projeto</button>
        <button type="button" class="text-red-600 hover:underline" @click="removeProject">Apagar</button>
      </div>
    </div>

    <FormAlert :message="error" />

    <div class="grid gap-4 md:grid-cols-4" data-test="board">
      <section
        v-for="column in columns"
        :key="column.value"
        class="flex min-h-48 flex-col rounded-2xl bg-slate-100 p-3"
        :class="dropTarget === column.value ? 'ring-2 ring-brand-500' : ''"
        :data-test="`column-${column.value}`"
      >
        <header class="mb-2 flex items-center justify-between px-1">
          <h2 class="text-sm font-semibold text-slate-700">{{ column.label }}</h2>
          <span class="text-xs text-slate-500">{{ column.tasks.length }}</span>
        </header>

        <ul
          class="flex flex-1 flex-col gap-2"
          @dragover.prevent="dropTarget = column.value"
          @dragleave="dropTarget = null"
          @drop.prevent="onDrop(column, $event)"
        >
          <li
            v-for="task in column.tasks"
            :key="task.id"
            :data-card="task.id"
            :draggable="canManageTasks"
            class="cursor-pointer rounded-xl bg-white p-3 text-sm shadow-sm ring-1 ring-slate-200 hover:ring-brand-500"
            :class="dragging === task.id ? 'opacity-50' : ''"
            @dragstart="onDragStart(task, $event)"
            @dragend="dragging = null"
            @click="modal = { task }"
          >
            <p class="font-medium" :class="task.status.value === 'done' ? 'text-slate-400 line-through' : ''">{{ task.title }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
              <span class="rounded px-1.5 py-0.5" :class="priorityClass[task.priority.value]">{{ task.priority.label }}</span>
              <span v-if="task.due_on" class="text-slate-500">{{ new Date(`${task.due_on}T12:00:00`).toLocaleDateString('pt-BR') }}</span>
              <span v-if="task.assignee" class="ml-auto text-slate-600">{{ task.assignee.name }}</span>
            </div>
          </li>
        </ul>

        <button
          v-if="canManageTasks && project && ['active', 'paused'].includes(project.status.value)"
          type="button"
          class="mt-2 rounded-lg px-2 py-1 text-left text-sm text-slate-500 hover:bg-white hover:text-brand-600"
          @click="modal = { task: null, status: column.value }"
        >
          + Adicionar tarefa
        </button>
      </section>
    </div>

    <TaskModal
      v-if="modal"
      :project-id="id"
      :task="modal.task"
      :status="modal.status"
      :members="members"
      :options="options"
      :can-edit="canManageTasks"
      @close="modal = null"
      @saved="onSaved"
      @removed="onRemoved"
    />
    <ProjectFormModal v-if="editingProject && project" :project="project" @close="editingProject = false" @saved="onProjectSaved" />
  </AppLayout>
</template>
