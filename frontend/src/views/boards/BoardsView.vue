<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { useApiForm } from '@/composables/useApiForm'
import AppLayout from '@/layouts/AppLayout.vue'
import { boardsApi } from '@/services/boards'
import { toApiError } from '@/services/http'
import { projectsApi } from '@/services/projects'
import { useCompanyStore } from '@/stores/company'
import type { Board, Paginated, Project } from '@/types/api'

const router = useRouter()
const company = useCompanyStore()
const canManage = computed(() => company.can('boards.manage'))

const page = ref<Paginated<Board> | null>(null)
const search = ref('')
const error = ref<string | null>(null)
const creating = ref(false)
const projects = ref<Project[]>([])
const form = reactive({ title: '', project_id: '' })
const save = useApiForm()

const updated = (value: string) =>
  new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })

async function load() {
  error.value = null
  try {
    page.value = await boardsApi.list({ search: search.value || undefined })
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

async function openCreate() {
  creating.value = true
  form.title = ''
  form.project_id = ''
  if (projects.value.length === 0 && company.can('projects.view')) {
    projects.value = (await projectsApi.list({ status: 'active', per_page: 100 }).catch(() => ({ data: [] as Project[] }))).data
  }
}

async function create() {
  const board = await save.submit(() => boardsApi.create({ title: form.title, project_id: form.project_id || null }))
  if (board) await router.push({ name: 'board', params: { id: board.id } })
}

async function remove(board: Board) {
  if (!window.confirm(`Apagar o board "${board.title}"? Isso não pode ser desfeito.`)) return
  try {
    await boardsApi.remove(board.id)
    await load()
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})
onMounted(load)
</script>

<template>
  <AppLayout wide>
    <PageHeader title="Boards" subtitle="Quadros livres para rascunhar ideias, mapas do site e fluxos com o cliente." :breadcrumbs="[{ label: 'Geral' }, { label: 'Boards' }]">
      <template #actions>
        <div class="relative w-full sm:w-60">
          <AppIcon name="search" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-fg-subtle" />
          <input v-model="search" type="search" placeholder="Buscar board" aria-label="Buscar board" class="field w-full pl-9" />
        </div>
        <button v-if="canManage" type="button" class="btn-primary" data-test="new-board" @click="openCreate"><AppIcon name="plus" :size="16" />Novo board</button>
      </template>
    </PageHeader>

    <FormAlert :message="error" />

    <div v-if="page && page.data.length === 0" class="card">
      <EmptyState icon="board" title="Nenhum board ainda." description="Use um board para rascunhar ideias, mapas do site e fluxos com o cliente." />
    </div>
    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" data-test="boards">
      <li v-for="board in page?.data ?? []" :key="board.id" class="card group relative overflow-hidden transition-colors hover:border-line-strong">
        <RouterLink :to="{ name: 'board', params: { id: board.id } }" class="block">
          <div class="relative h-32 border-b border-line bg-canvas" style="background-image: radial-gradient(rgb(255 255 255 / 0.09) 1px, transparent 1px); background-size: 14px 14px">
            <span class="absolute top-6 left-8 h-10 w-14 -rotate-3 rounded bg-amber-300/80" />
            <span class="absolute top-10 left-28 h-12 w-20 rounded-lg border-2 border-brand-500/70" />
            <span class="absolute top-16 right-10 h-8 w-8 rounded-full border-2 border-emerald-500/70" />
          </div>
          <div class="p-4">
            <p class="truncate font-semibold text-fg">{{ board.title }}</p>
            <p class="mt-0.5 truncate text-xs text-fg-muted">
              {{ board.project?.name ?? 'Sem projeto' }} · {{ board.elements_count ?? 0 }} {{ board.elements_count === 1 ? 'elemento' : 'elementos' }}
            </p>
            <p class="mt-2 text-xs text-fg-subtle">Alterado em {{ updated(board.updated_at) }}</p>
          </div>
        </RouterLink>
        <button
          v-if="canManage"
          type="button"
          class="absolute top-3 right-3 rounded-lg border border-line bg-surface/90 p-1.5 text-fg-muted opacity-0 backdrop-blur transition group-hover:opacity-100 hover:text-red-300 focus:opacity-100"
          :aria-label="`Apagar ${board.title}`"
          title="Apagar"
          @click="remove(board)"
        >
          <AppIcon name="trash" :size="15" />
        </button>
      </li>
    </ul>

    <BaseModal v-if="creating" title="Novo board" @close="creating = false">
      <form class="space-y-4" novalidate @submit.prevent="create">
        <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
        <BaseInput id="board-title" v-model="form.title" label="Nome" :error="save.fieldErrors.value.title" />
        <div>
          <label for="board-project" class="field-label">Projeto (opcional)</label>
          <select id="board-project" v-model="form.project_id" class="field w-full">
            <option value="">Sem projeto</option>
            <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
          </select>
        </div>
        <BaseButton :loading="save.submitting.value">Criar e abrir</BaseButton>
      </form>
    </BaseModal>
  </AppLayout>
</template>
