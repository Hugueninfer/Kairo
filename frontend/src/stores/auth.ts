import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginPayload, type RegisterPayload } from '@/services/auth'
import type { User } from '@/types/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  // Indica se já perguntamos à API quem é o usuário (a sessão vive num cookie HttpOnly).
  const loaded = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  async function fetchUser(): Promise<void> {
    try {
      user.value = await authApi.me()
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
  }

  /** Devolve true quando a conta pede o código do aplicativo (login em duas etapas). */
  async function login(payload: LoginPayload): Promise<boolean> {
    const result = await authApi.login(payload)
    if ('twoFactor' in result) return true
    user.value = result.user
    loaded.value = true
    return false
  }

  async function confirmLogin(code: string): Promise<void> {
    user.value = await authApi.twoFactorChallenge(code)
    loaded.value = true
  }

  async function register(payload: RegisterPayload): Promise<void> {
    user.value = await authApi.register(payload)
    loaded.value = true
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      user.value = null
      // Fecha o WebSocket dos boards, se estiver aberto (carregado só quando um board foi aberto).
      void import('@/services/realtime').then(({ disconnectRealtime }) => disconnectRealtime())
    }
  }

  return { user, loaded, isAuthenticated, fetchUser, login, confirmLogin, register, logout }
})
