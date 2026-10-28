import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/types/api'

const user: User = { id: 1, name: 'Ana', email: 'ana@kairo.test', email_verified: true, two_factor_enabled: false, created_at: null }

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('guarda o usuário depois do login', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue({ user })
    const auth = useAuthStore()

    await auth.login({ email: 'ana@kairo.test', password: 'x' })

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.user?.name).toBe('Ana')
  })

  it('marca como visitante quando a API responde 401', async () => {
    vi.spyOn(authApi, 'me').mockRejectedValue(new Error('401'))
    const auth = useAuthStore()

    await auth.fetchUser()

    expect(auth.loaded).toBe(true)
    expect(auth.isAuthenticated).toBe(false)
  })

  it('limpa o usuário no logout mesmo se a API falhar', async () => {
    vi.spyOn(authApi, 'logout').mockRejectedValue(new Error('offline'))
    const auth = useAuthStore()
    auth.user = user

    await expect(auth.logout()).rejects.toThrow()
    expect(auth.user).toBeNull()
  })
})
