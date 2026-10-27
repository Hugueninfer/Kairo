<script setup lang="ts">
import { isAxiosError } from 'axios'
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import BoardCanvas from '@/components/boards/BoardCanvas.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useBoardDocument, type BoardDocument } from '@/composables/boardDocument'
import { useAutosave } from '@/composables/useAutosave'
import AppLayout from '@/layouts/AppLayout.vue'
import { boardsApi } from '@/services/boards'
import { toApiError } from '@/services/http'
import { useCompanyStore } from '@/stores/company'
import type { Board } from '@/types/api'

const props = defineProps<{ id: string }>()

const company = useCompanyStore()
const editable = computed(() => company.can('boards.manage'))
const board = ref<Board | null>(null)
const doc = shallowRef<BoardDocument | null>(null)
const title = ref('')
const error = ref<string | null>(null)

const autosave = useAutosave(
  async () => {
    if (!doc.value) return
    const changes = doc.value.takeChanges()
    try {
      await boardsApi.sync(props.id, changes)
      error.value = null
    } catch (failure) {
      doc.value.restore(changes)
      if (isRejected(failure)) error.value = toApiError(failure).message
      throw failure
    }
  },
  () => doc.value?.hasChanges() ?? false,
  { isFatal: isRejected },
)

/** A API recusou o lote (validação ou regra, como o limite de elementos): repetir não resolve. */
function isRejected(failure: unknown): boolean {
  return isAxiosError(failure) && failure.response?.status === 422
}

const saveLabel = computed(
  () =>
    ({
      saved: 'Tudo salvo',
      pending: 'Salvando…',
      saving: 'Salvando…',
      error: 'Não foi possível salvar',
    })[autosave.state.value],
)

async function renameBoard() {
  const value = title.value.trim()
  if (!board.value || !value || value === board.value.title) {
    title.value = board.value?.title ?? ''
    return
  }
  try {
    board.value = { ...board.value, ...(await boardsApi.update(props.id, { title: value, project_id: board.value.project?.id ?? null })) }
  } catch (failure) {
    error.value = toApiError(failure).message
    title.value = board.value.title
  }
}

function warnUnsaved(event: BeforeUnloadEvent) {
  if (doc.value?.hasChanges() || autosave.state.value === 'saving') event.preventDefault()
}

onBeforeRouteLeave(() => autosave.flush())

onMounted(async () => {
  window.addEventListener('beforeunload', warnUnsaved)
  try {
    board.value = await boardsApi.get(props.id)
    title.value = board.value.title
    doc.value = useBoardDocument(board.value.elements ?? [])
  } catch (failure) {
    error.value = toApiError(failure).message
  }
})

onBeforeUnmount(() => window.removeEventListener('beforeunload', warnUnsaved))
</script>

<template>
  <AppLayout full>
    <div class="flex h-[calc(100vh-6.5rem)] flex-col gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <RouterLink :to="{ name: 'boards' }" class="text-sm text-brand-600 hover:underline">‹ Boards</RouterLink>
        <input
          v-if="board"
          v-model="title"
          :disabled="!editable"
          maxlength="120"
          aria-label="Nome do board"
          class="min-w-64 rounded-lg border border-transparent bg-transparent px-2 py-1 text-xl font-semibold hover:border-slate-300 focus:border-slate-300 focus:bg-white disabled:hover:border-transparent"
          data-test="board-title"
          @change="renameBoard"
          @keydown.enter="($event.target as HTMLInputElement).blur()"
        />
        <RouterLink v-if="board?.project" :to="{ name: 'project-board', params: { id: board.project.id } }" class="text-sm text-slate-500 hover:text-brand-600">
          Projeto {{ board.project.name }}
        </RouterLink>
        <span v-if="!editable" class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Somente leitura</span>
        <span
          v-if="editable && doc"
          class="ml-auto text-sm"
          :class="autosave.state.value === 'error' ? 'text-red-600' : 'text-slate-500'"
          data-test="save-state"
          >{{ saveLabel }}</span
        >
      </div>
      <FormAlert :message="error" />
      <BoardCanvas v-if="doc" class="min-h-0 flex-1" :doc="doc" :editable="editable" @change="autosave.schedule" />
    </div>
  </AppLayout>
</template>
