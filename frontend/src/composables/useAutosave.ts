import { onBeforeUnmount, ref } from 'vue'

export type SaveState = 'saved' | 'pending' | 'saving' | 'error'

export interface AutosaveOptions {
  delay?: number
  retryDelay?: number
  /** Erro que não adianta repetir (ex.: recusado pela regra de negócio): para de tentar até a próxima mudança. */
  isFatal?: (error: unknown) => boolean
}

/**
 * Agrupa as mudanças e grava depois de um tempo sem mexer. Se a rede falhar, tenta de novo sozinho.
 */
export function useAutosave(save: () => Promise<void>, hasChanges: () => boolean, options: AutosaveOptions = {}) {
  const { delay = 800, retryDelay = 5000, isFatal = () => false } = options
  const state = ref<SaveState>('saved')
  let timer: ReturnType<typeof setTimeout> | undefined
  let running: Promise<void> | null = null

  async function flush(): Promise<void> {
    clearTimeout(timer)
    if (running) await running
    if (!hasChanges()) {
      if (state.value !== 'error') state.value = 'saved'
      return
    }
    state.value = 'saving'
    running = save()
      .then(() => {
        state.value = hasChanges() ? 'pending' : 'saved'
        if (hasChanges()) schedule()
      })
      .catch((error: unknown) => {
        state.value = 'error'
        if (!isFatal(error)) timer = setTimeout(flush, retryDelay)
      })
      .finally(() => {
        running = null
      })
    await running
  }

  function schedule() {
    state.value = state.value === 'saving' ? 'saving' : 'pending'
    clearTimeout(timer)
    timer = setTimeout(flush, delay)
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { state, schedule, flush }
}
