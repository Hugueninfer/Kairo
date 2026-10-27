<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
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
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Boards</h1>
      <div class="flex gap-2">
        <input v-model="search" type="search" placeholder="Buscar board" aria-label="Buscar board" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        <button v-if="canManage" type="button" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" @click="openCreate">
          Novo board
        </button>
      </div>
    </div>

    <FormAlert :message="error" />

    <p v-if="page && page.data.length === 0" class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
      Nenhum board ainda. Use um board para rascunhar ideias, mapas do site e fluxos com o cliente.
    </p>
    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-test="boards">
      <li v-for="board in page?.data ?? []" :key="board.id" class="group relative rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 hover:ring-brand-300">
        <RouterLink :to="{ name: 'board', params: { id: board.id } }" class="block p-5">
          <div class="mb-3 h-24 rounded-lg bg-slate-50" style="background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 14px 14px" />
          <p class="font-semibold">{{ board.title }}</p>
          <p class="text-xs text-slate-500">
            {{ board.project?.name ?? 'Sem projeto' }} · {{ board.elements_count ?? 0 }} {{ board.elements_count === 1 ? 'elemento' : 'elementos' }}
          </p>
          <p class="text-xs text-slate-400">Alterado em {{ updated(board.updated_at) }}</p>
        </RouterLink>
        <button
          v-if="canManage"
          type="button"
          class="absolute right-4 top-4 hidden rounded bg-white/90 px-2 py-0.5 text-xs text-red-600 shadow group-hover:block"
          @click="remove(board)"
        >
          Apagar
        </button>
      </li>
    </ul>

    <BaseModal v-if="creating" title="Novo board" @close="creating = false">
      <form class="space-y-4" novalidate @submit.prevent="create">
        <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
        <BaseInput id="board-title" v-model="form.title" label="Nome" :error="save.fieldErrors.value.title" />
        <div>
          <label for="board-project" class="mb-1 block text-sm font-medium text-slate-700">Projeto (opcional)</label>
          <select id="board-project" v-model="form.project_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Sem projeto</option>
            <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
          </select>
        </div>
        <BaseButton :loading="save.submitting.value">Criar e abrir</BaseButton>
      </form>
    </BaseModal>
  </AppLayout>
</template>
