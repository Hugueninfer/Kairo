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

export interface Company {
  id: string
  name: string
  document: string | null
  currency: string
  timezone: string
  default_hourly_rate: string | null
  is_owner: boolean
  is_current: boolean
}

export interface Member {
  id: number
  name: string
  email: string
  is_owner: boolean
}

export interface Invitation {
  id: string
  email: string
  status: { value: string; label: string }
  expires_at: string
  invited_by?: string
}

export interface InvitationPreview {
  company_name: string
  invited_by: string
  email: string
  expires_at: string
}
