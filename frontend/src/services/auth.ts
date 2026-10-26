import { ensureCsrfCookie, http } from './http'
import type { ApiResource, User } from '@/types/api'

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export const authApi = {
  async me(): Promise<User> {
    const { data } = await http.get<ApiResource<User>>('/api/v1/auth/me')
    return data.data
  },

  async login(payload: LoginPayload): Promise<User> {
    await ensureCsrfCookie()
    const { data } = await http.post<ApiResource<User>>('/api/v1/auth/login', payload)
    return data.data
  },

  async register(payload: RegisterPayload): Promise<User> {
    await ensureCsrfCookie()
    const { data } = await http.post<ApiResource<User>>('/api/v1/auth/register', payload)
    return data.data
  },

  async logout(): Promise<void> {
    await http.post('/api/v1/auth/logout')
  },

  async forgotPassword(email: string): Promise<string> {
    await ensureCsrfCookie()
    const { data } = await http.post<{ message: string }>('/api/v1/auth/forgot-password', { email })
    return data.message
  },

  async resetPassword(payload: ResetPasswordPayload): Promise<string> {
    await ensureCsrfCookie()
    const { data } = await http.post<{ message: string }>('/api/v1/auth/reset-password', payload)
    return data.message
  },

  async resendVerification(): Promise<string> {
    const { data } = await http.post<{ message: string }>('/api/v1/auth/email/verification-notification')
    return data.message
  },
}
