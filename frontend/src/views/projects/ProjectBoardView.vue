<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue'
import TaskModal from '@/components/projects/TaskModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge, { type BadgeTone } from '@/components/ui/StatusBadge.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { statusTone } from '@/composables/statusTone'
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

const priorityTone: Record<string, BadgeTone> = { low: 'neutral', medium: 'blue', high: 'amber', urgent: 'red' }
const columnDot: Record<string, string> = { todo: 'bg-zinc-400', in_progress: 'bg-brand-500', review: 'bg-violet-500', done: 'bg-emerald-500' }

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
    <PageHeader v-if="project" :title="project.name" :breadcrumbs="[{ label: 'Projetos', to: { name: 'projects' } }, { label: project.name }]">
      <template #subtitle>
        <span class="flex flex-wrap items-center gap-2">
          {{ project.client?.name ?? 'Sem cliente' }}
          <StatusBadge :tone="statusTone('project', project.status.value)">{{ project.status.label }}</StatusBadge>
          <StatusBadge tone="neutral" :dot="false">{{ project.billing_type.label }}</StatusBadge>
        </span>
      </template>
      <template v-if="canManageProject" #actions>
        <button type="button" class="btn-secondary" @click="editingProject = true"><AppIcon name="edit" :size="16" />Editar projeto</button>
        <button type="button" class="btn-danger" @click="removeProject"><AppIcon name="trash" :size="16" />Apagar</button>
      </template>
    </PageHeader>

    <FormAlert :message="error" />

    <div class="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 lg:grid lg:grid-cols-4 lg:overflow-visible" data-test="board">
      <section
        v-for="column in columns"
        :key="column.value"
        class="flex min-h-64 w-[82vw] max-w-80 shrink-0 snap-start flex-col rounded-xl border border-line bg-surface p-3 sm:w-72 lg:w-auto lg:max-w-none"
        :class="dropTarget === column.value ? 'ring-2 ring-brand-500' : ''"
        :data-test="`column-${column.value}`"
      >
        <header class="mb-3 flex items-center justify-between px-1">
          <h2 class="flex items-center gap-2 text-sm font-semibold text-fg">
            <span class="h-2 w-2 rounded-full" :class="columnDot[column.value] ?? 'bg-zinc-500'" aria-hidden="true" />{{ column.label }}
          </h2>
          <span class="rounded-md bg-elevated px-1.5 text-xs text-fg-soft">{{ column.tasks.length }}</span>
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
            class="cursor-pointer rounded-lg border border-line bg-surface-2 p-3 text-sm transition-colors hover:border-brand-500/60"
            :class="dragging === task.id ? 'opacity-50' : ''"
            @dragstart="onDragStart(task, $event)"
            @dragend="dragging = null"
            @click="modal = { task }"
          >
            <p class="font-medium" :class="task.status.value === 'done' ? 'text-fg-subtle line-through' : ''">{{ task.title }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
              <StatusBadge :tone="priorityTone[task.priority.value] ?? 'neutral'">{{ task.priority.label }}</StatusBadge>
              <span v-if="task.due_on" class="inline-flex items-center gap-1 text-fg-muted"><AppIcon name="calendar" :size="12" />{{ new Date(`${task.due_on}T12:00:00`).toLocaleDateString('pt-BR') }}</span>
              <span v-if="task.assignee" class="ml-auto" :title="task.assignee.name"><UserAvatar :name="task.assignee.name" :size="22" /></span>
            </div>
          </li>
        </ul>

        <button
          v-if="canManageTasks && project && ['active', 'paused'].includes(project.status.value)"
          type="button"
          class="mt-2 flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-left text-sm text-fg-muted hover:bg-surface-2 hover:text-fg"
          @click="modal = { task: null, status: column.value }"
        >
          <AppIcon name="plus" :size="15" />Adicionar tarefa
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
