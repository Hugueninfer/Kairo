<script setup lang="ts">
import { isAxiosError } from 'axios'
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import BoardCanvas from '@/components/boards/BoardCanvas.vue'
import BoardVersionsPanel from '@/components/boards/BoardVersionsPanel.vue'
import ShareBoardModal from '@/components/boards/ShareBoardModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useBoardDocument, type BoardDocument } from '@/composables/boardDocument'
import { useAutosave } from '@/composables/useAutosave'
import { memberColor, useBoardRealtime, type BoardChangedEvent } from '@/composables/useBoardRealtime'
import AppLayout from '@/layouts/AppLayout.vue'
import { boardsApi } from '@/services/boards'
import { toApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'
import type { Board, BoardVersion } from '@/types/api'

const props = defineProps<{ id: string }>()

const auth = useAuthStore()
const company = useCompanyStore()
const editable = computed(() => company.can('boards.manage'))
const board = ref<Board | null>(null)
const doc = shallowRef<BoardDocument | null>(null)
const title = ref('')
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const showVersions = ref(false)
const showShare = ref(false)
const preview = shallowRef<{ version: BoardVersion; doc: BoardDocument } | null>(null)
const restoring = ref(false)
const versionsPanel = ref<InstanceType<typeof BoardVersionsPanel> | null>(null)

// Entra no canal antes de buscar o board: nada que outra pessoa salvar no meio do caminho se perde.
const live = useBoardRealtime(
  props.id,
  { id: auth.user?.id ?? 0 },
  { onChanged, onResync: resync, onVersionSaved: () => void versionsPanel.value?.load() },
)
const others = computed(() => live.members.value.filter((member) => member.id !== auth.user?.id))
const cursors = computed(() => (preview.value ? [] : [...live.cursors.values()]))

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

/** Alguém salvou: aplica o lote, ou recarrega tudo quando foi uma versão restaurada (ou um lote grande). */
function onChanged(event: BoardChangedEvent) {
  if (!doc.value) return
  if (event.reload) {
    void resync()
    return
  }
  doc.value.applyRemote(event.upserts, event.deletes)
}

async function resync() {
  if (!doc.value) return
  try {
    const fresh = await boardsApi.get(props.id)
    board.value = { ...fresh, elements: undefined }
    if (!title.value || title.value === board.value.title) title.value = fresh.title
    doc.value.reset(fresh.elements ?? [])
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

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

function openPreview(version: BoardVersion) {
  preview.value = { version, doc: useBoardDocument(version.elements ?? []) }
}

const previewWhen = computed(() =>
  preview.value
    ? new Date(preview.value.version.created_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
    : '',
)

async function restorePreview() {
  if (!preview.value || !doc.value) return
  if (!window.confirm(`Voltar o board para a versão de ${previewWhen.value}? O estado atual fica guardado no histórico.`)) return
  restoring.value = true
  try {
    await autosave.flush()
    const restored = await boardsApi.restore(props.id, preview.value.version.id)
    doc.value.reset(restored.elements ?? [])
    board.value = { ...restored, elements: undefined }
    preview.value = null
    notice.value = 'Versão restaurada. O estado anterior ficou no histórico.'
    void versionsPanel.value?.load()
  } catch (failure) {
    error.value = toApiError(failure).message
  } finally {
    restoring.value = false
  }
}

function closeVersions() {
  showVersions.value = false
  preview.value = null
}

const initials = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')

function warnUnsaved(event: BeforeUnloadEvent) {
  if (doc.value?.hasChanges() || autosave.state.value === 'saving') event.preventDefault()
}

onBeforeRouteLeave(() => autosave.flush())

onMounted(async () => {
  window.addEventListener('beforeunload', warnUnsaved)
  try {
    const loaded = await boardsApi.get(props.id)
    board.value = { ...loaded, elements: undefined }
    title.value = loaded.title
    doc.value = useBoardDocument(loaded.elements ?? [])
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

        <div class="ml-auto flex items-center gap-3">
          <div v-if="others.length" class="flex -space-x-2" data-test="presence">
            <span
              v-for="member in others.slice(0, 5)"
              :key="member.id"
              class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold text-white ring-2 ring-white"
              :style="{ background: memberColor(member.id) }"
              :title="`${member.name} está no board`"
              >{{ initials(member.name) }}</span
            >
            <span v-if="others.length > 5" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700 ring-2 ring-white"
              >+{{ others.length - 5 }}</span
            >
          </div>
          <span v-if="live.status.value === 'offline'" class="text-xs text-amber-700" data-test="realtime-offline">Sem conexão ao vivo</span>
          <span
            v-if="editable && doc"
            class="text-sm"
            :class="autosave.state.value === 'error' ? 'text-red-600' : 'text-slate-500'"
            data-test="save-state"
            >{{ saveLabel }}</span
          >
          <button
            v-if="board"
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50"
            :aria-pressed="showVersions"
            data-test="open-versions"
            @click="showVersions ? closeVersions() : (showVersions = true)"
          >
            Versões
          </button>
          <button
            v-if="board && editable"
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50"
            data-test="open-share"
            @click="showShare = true"
          >
            {{ board.share_url ? 'Compartilhado' : 'Compartilhar' }}
          </button>
        </div>
      </div>
      <FormAlert :message="error" />
      <FormAlert :message="notice" variant="success" />
      <div
        v-if="preview"
        class="flex flex-wrap items-center gap-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200"
        data-test="preview-banner"
      >
        <span>
          Você está vendo a versão de {{ previewWhen }}<template v-if="preview.version.label"> ({{ preview.version.label }})</template>. Nada muda até você restaurar.
        </span>
        <button
          v-if="editable"
          type="button"
          class="rounded-lg bg-amber-600 px-3 py-1 font-semibold text-white hover:bg-amber-700 disabled:opacity-60"
          :disabled="restoring"
          data-test="restore-version"
          @click="restorePreview"
        >
          Restaurar esta versão
        </button>
        <button type="button" class="hover:underline" @click="preview = null">Voltar ao board atual</button>
      </div>
      <div class="flex min-h-0 flex-1 gap-3">
        <BoardCanvas
          v-if="preview"
          :key="`preview-${preview.version.id}`"
          class="min-h-0 min-w-0 flex-1"
          :doc="preview.doc"
          :editable="false"
        />
        <BoardCanvas
          v-else-if="doc"
          class="min-h-0 min-w-0 flex-1"
          :doc="doc"
          :editable="editable"
          :cursors="cursors"
          @change="autosave.schedule"
          @pointer="live.moveCursor"
        />
        <BoardVersionsPanel
          v-if="showVersions && board"
          ref="versionsPanel"
          :board-id="board.id"
          :editable="editable"
          :previewing="preview?.version.id ?? null"
          @close="closeVersions"
          @preview="openPreview"
        />
      </div>
    </div>
    <ShareBoardModal v-if="showShare && board" :board="board" @close="showShare = false" @updated="board = { ...board, ...$event, elements: undefined }" />
  </AppLayout>
</template>
