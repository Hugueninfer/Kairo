import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import type { ApiErrorBody } from '@/types/api'

// Cliente HTTP único da aplicação. O front nunca fala com o banco: tudo passa pela API.
// withCredentials + withXSRFToken fazem o Sanctum (modo SPA) usar cookie HttpOnly e proteção CSRF,
// sem guardar token no localStorage.
export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export async function ensureCsrfCookie(): Promise<void> {
  await http.get('/sanctum/csrf-cookie')
}

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

// 419 = token CSRF expirado. Renova o cookie e repete a requisição uma única vez.
http.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config as RetriableConfig | undefined

  if (error.response?.status === 419 && config && !config._csrfRetried) {
    config._csrfRetried = true
    await ensureCsrfCookie()
    return http.request(config)
  }

  return Promise.reject(error)
})

/** Extrai o corpo de erro padronizado da API, com mensagem genérica quando não houver. */
export function toApiError(error: unknown): ApiErrorBody {
  if (axios.isAxiosError<ApiErrorBody>(error) && error.response?.data?.message) {
    return error.response.data
  }

  return { message: 'Não foi possível concluir a ação. Tente novamente.' }
}
