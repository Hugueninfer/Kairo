import { ref } from 'vue'
import { projectsApi } from '@/services/projects'
import type { Options } from '@/types/api'

// As opções não mudam durante a sessão: busca uma vez e reaproveita entre telas.
const options = ref<Options | null>(null)
let pending: Promise<Options> | null = null

export function useOptions() {
  async function load(): Promise<Options> {
    if (options.value) return options.value
    pending ??= projectsApi.options().finally(() => {
      pending = null
    })
    options.value = await pending
    return options.value
  }

  return { options, load }
}
