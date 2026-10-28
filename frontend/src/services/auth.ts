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

/** Com login em duas etapas, a senha certa ainda não abre a sessão: falta o código. */
export type LoginResult = { user: User } | { twoFactor: true }

export interface TwoFactorSetup {
  secret: string
  otpauth_url: string
}

export const authApi = {
  async me(): Promise<User> {
    const { data } = await http.get<ApiResource<User>>('/api/v1/auth/me')
    return data.data
  },

  async login(payload: LoginPayload): Promise<LoginResult> {
    await ensureCsrfCookie()
    const { data } = await http.post<ApiResource<User> | { two_factor: true }>('/api/v1/auth/login', payload)
    return 'two_factor' in data ? { twoFactor: true } : { user: data.data }
  },

  async twoFactorChallenge(code: string): Promise<User> {
    const { data } = await http.post<ApiResource<User>>('/api/v1/auth/two-factor-challenge', { code })
    return data.data
  },

  async changePassword(payload: { current_password: string; password: string; password_confirmation: string }): Promise<void> {
    await http.put('/api/v1/auth/password', payload)
  },

  async startTwoFactor(password: string): Promise<TwoFactorSetup> {
    const { data } = await http.post<ApiResource<TwoFactorSetup>>('/api/v1/auth/two-factor', { password })
    return data.data
  },

  async confirmTwoFactor(code: string): Promise<string[]> {
    const { data } = await http.post<ApiResource<{ recovery_codes: string[] }>>('/api/v1/auth/two-factor/confirm', { code })
    return data.data.recovery_codes
  },

  async regenerateRecoveryCodes(password: string): Promise<string[]> {
    const { data } = await http.post<ApiResource<{ recovery_codes: string[] }>>('/api/v1/auth/two-factor/recovery-codes', { password })
    return data.data.recovery_codes
  },

  async disableTwoFactor(password: string): Promise<void> {
    await http.delete('/api/v1/auth/two-factor', { data: { password } })
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
