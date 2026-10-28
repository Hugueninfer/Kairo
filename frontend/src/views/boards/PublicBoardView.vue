<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef } from 'vue'
import { useRoute } from 'vue-router'
import BoardCanvas from '@/components/boards/BoardCanvas.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppLogo from '@/components/ui/AppLogo.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { useBoardDocument, type BoardDocument } from '@/composables/boardDocument'
import { publicBoardApi } from '@/services/boards'
import { toApiError } from '@/services/http'
import type { PublicBoard } from '@/types/api'

// Board compartilhado com o cliente: abre sem login, só para ver. A API confere o link.
const REFRESH_EVERY_MS = 30_000

const route = useRoute()
const token = String(route.params.token)
const board = ref<PublicBoard | null>(null)
const doc = shallowRef<BoardDocument | null>(null)
const error = ref<string | null>(null)
let timer: ReturnType<typeof setInterval> | undefined

async function load() {
  try {
    const fresh = await publicBoardApi.get(token)
    board.value = fresh
    if (doc.value) doc.value.reset(fresh.elements)
    else doc.value = useBoardDocument(fresh.elements)
    error.value = null
  } catch (failure) {
    error.value = toApiError(failure).message
    if (!board.value) clearInterval(timer)
  }
}

const updatedAt = (value: string) => new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })

onMounted(async () => {
  await load()
  // Sem conta não há canal ao vivo; o quadro se atualiza sozinho de tempos em tempos.
  if (board.value) timer = setInterval(() => void load(), REFRESH_EVERY_MS)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="flex h-dvh flex-col gap-3 p-4">
    <header v-if="board" class="flex flex-wrap items-center gap-x-4 gap-y-2">
      <AppLogo :height="18" />
      <span class="hidden h-5 w-px bg-line sm:block" aria-hidden="true" />
      <div class="min-w-0">
        <h1 class="truncate text-lg font-semibold" data-test="public-board-title">{{ board.title }}</h1>
        <p class="text-xs text-fg-muted">{{ board.company.name }} · atualizado em {{ updatedAt(board.updated_at) }}</p>
      </div>
      <StatusBadge tone="neutral" :dot="false" class="ml-auto"><AppIcon name="eye" :size="12" />Somente leitura</StatusBadge>
    </header>
    <FormAlert :message="error && !board ? 'Este link não está mais disponível. Peça um novo a quem compartilhou.' : error" />
    <BoardCanvas v-if="doc" class="min-h-0 flex-1" :doc="doc" :editable="false" />
  </div>
</template>
