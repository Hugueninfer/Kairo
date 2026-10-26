import { ref } from 'vue'
import { toApiError } from '@/services/http'

/**
 * Controla envio, erros de campo (422) e mensagem geral de um formulário que chama a API.
 */
export function useApiForm() {
  const submitting = ref(false)
  const message = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string>>({})

  async function submit<T>(action: () => Promise<T>): Promise<T | undefined> {
    submitting.value = true
    message.value = null
    fieldErrors.value = {}

    try {
      return await action()
    } catch (error) {
      const body = toApiError(error)
      message.value = body.message
      fieldErrors.value = Object.fromEntries(
        Object.entries(body.errors ?? {}).map(([field, messages]) => [field, messages[0] ?? '']),
      )
      return undefined
    } finally {
      submitting.value = false
    }
  }

  return { submitting, message, fieldErrors, submit }
}
