export interface User {
  id: number
  name: string
  email: string
  email_verified: boolean
  created_at: string | null
}

export interface ApiResource<T> {
  data: T
}

/** Formato padronizado de erro da API (BusinessException, 401, 403, 404, 422). */
export interface ApiErrorBody {
  message: string
  code?: string
  errors?: Record<string, string[]>
}
