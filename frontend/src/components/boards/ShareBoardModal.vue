<script setup lang="ts">
import { ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { boardsApi } from '@/services/boards'
import { toApiError } from '@/services/http'
import type { Board } from '@/types/api'

const props = defineProps<{ board: Board }>()
const emit = defineEmits<{ close: []; updated: [board: Board] }>()

const busy = ref(false)
const error = ref<string | null>(null)
const copied = ref(false)

async function run(action: () => Promise<Board>) {
  busy.value = true
  error.value = null
  try {
    emit('updated', await action())
  } catch (failure) {
    error.value = toApiError(failure).message
  } finally {
    busy.value = false
  }
}

function turnOn() {
  void run(() => boardsApi.share(props.board.id))
}

function turnOff() {
  if (!window.confirm('Desligar o link? Quem tem o endereço atual perde o acesso.')) return
  void run(() => boardsApi.unshare(props.board.id))
}

async function copy() {
  if (!props.board.share_url) return
  await navigator.clipboard.writeText(props.board.share_url)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}
</script>

<template>
  <BaseModal title="Compartilhar com o cliente" @close="emit('close')">
    <div class="space-y-4">
      <p class="text-sm text-slate-600">
        Quem tiver o link vê o board sem precisar de conta, só para leitura. Ninguém consegue editar por ele, e você pode desligar quando quiser.
      </p>
      <FormAlert :message="error" />
      <template v-if="board.share_url">
        <div class="flex gap-2">
          <input
            :value="board.share_url"
            readonly
            aria-label="Link de leitura"
            class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm"
            data-test="share-url"
            @focus="($event.target as HTMLInputElement).select()"
          />
          <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" @click="copy">
            {{ copied ? 'Copiado' : 'Copiar' }}
          </button>
        </div>
        <button type="button" class="w-full text-sm text-red-600 hover:underline" :disabled="busy" data-test="share-off" @click="turnOff">
          Desligar link
        </button>
      </template>
      <BaseButton v-else type="button" :loading="busy" data-test="share-on" @click="turnOn">Criar link de leitura</BaseButton>
    </div>
  </BaseModal>
</template>
