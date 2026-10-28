<script setup lang="ts">
import { onMounted, ref } from 'vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { boardsApi } from '@/services/boards'
import { toApiError } from '@/services/http'
import type { BoardVersion } from '@/types/api'

const props = defineProps<{ boardId: string; editable: boolean; previewing: string | null }>()
const emit = defineEmits<{ close: []; preview: [version: BoardVersion] }>()

const versions = ref<BoardVersion[]>([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const label = ref('')
const saving = ref(false)
const opening = ref<string | null>(null)

const when = (value: string) =>
  new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })

async function load(next = 1) {
  loading.value = true
  try {
    const result = await boardsApi.versions(props.boardId, next)
    versions.value = next === 1 ? result.data : [...versions.value, ...result.data]
    page.value = result.meta.current_page
    lastPage.value = result.meta.last_page
    error.value = null
  } catch (failure) {
    error.value = toApiError(failure).message
  } finally {
    loading.value = false
  }
}

/**
 * A versão é guardada na fila. Com o tempo real ligado a lista se atualiza quando ela fica pronta;
 * a recarga logo depois cobre quando não há conexão ao vivo.
 */
async function save() {
  saving.value = true
  notice.value = null
  try {
    await boardsApi.saveVersion(props.boardId, label.value.trim() || null)
    label.value = ''
    notice.value = 'Versão salva.'
    setTimeout(() => void load(), 3000)
  } catch (failure) {
    error.value = toApiError(failure).message
  } finally {
    saving.value = false
  }
}

async function open(version: BoardVersion) {
  opening.value = version.id
  try {
    emit('preview', await boardsApi.version(props.boardId, version.id))
  } catch (failure) {
    error.value = toApiError(failure).message
  } finally {
    opening.value = null
  }
}

onMounted(() => load())

defineExpose({ load })
</script>

<template>
  <aside class="flex w-80 shrink-0 flex-col gap-3 overflow-hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200" data-test="versions-panel">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold">Histórico de versões</h2>
      <button type="button" class="text-slate-400 hover:text-slate-600" aria-label="Fechar histórico" @click="emit('close')">✕</button>
    </div>
    <form v-if="editable" class="flex gap-2" @submit.prevent="save">
      <input
        v-model="label"
        maxlength="80"
        placeholder="Nome (opcional)"
        aria-label="Nome da versão"
        class="min-w-0 flex-1 rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
      />
      <button type="submit" :disabled="saving" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60" data-test="save-version">
        Salvar versão
      </button>
    </form>
    <FormAlert :message="notice" variant="success" />
    <FormAlert :message="error" />
    <p class="text-xs text-slate-500">O Kairo também guarda uma versão automática a cada 10 minutos de edição.</p>
    <ul class="-mx-2 min-h-0 flex-1 space-y-1 overflow-y-auto px-2">
      <li v-for="version in versions" :key="version.id">
        <button
          type="button"
          class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50"
          :class="previewing === version.id ? 'bg-brand-50 ring-1 ring-brand-200' : ''"
          :disabled="opening !== null"
          data-test="version"
          @click="open(version)"
        >
          <span class="block font-medium text-slate-800">{{ version.label ?? (version.automatic ? 'Versão automática' : 'Versão salva') }}</span>
          <span class="block text-xs text-slate-500">
            {{ when(version.created_at) }}<template v-if="version.author"> · {{ version.author.name }}</template> · {{ version.element_count }}
            {{ version.element_count === 1 ? 'elemento' : 'elementos' }}
          </span>
        </button>
      </li>
      <li v-if="!loading && versions.length === 0" class="px-3 py-6 text-center text-sm text-slate-500">Nenhuma versão ainda.</li>
    </ul>
    <button v-if="page < lastPage" type="button" class="text-sm text-brand-600 hover:underline" :disabled="loading" @click="load(page + 1)">Carregar mais</button>
  </aside>
</template>
