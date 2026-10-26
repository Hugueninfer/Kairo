import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { RouteLocationNormalized } from 'vue-router'
import { authGuard } from '@/router'
import { authApi } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

function routeTo(path: string, meta: RouteLocationNormalized['meta']): RouteLocationNormalized {
  return { fullPath: path, path, meta } as RouteLocationNormalized
}

describe('authGuard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('manda o visitante para o login guardando o destino', async () => {
    vi.spyOn(authApi, 'me').mockRejectedValue(new Error('401'))

    const result = await authGuard(routeTo('/projetos', { requiresAuth: true }))

    expect(result).toEqual({ name: 'login', query: { redirect: '/projetos' } })
  })

  it('tira o usuário logado das telas de visitante', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Ana', email: 'a@k.test', email_verified: true, created_at: null }
    auth.loaded = true

    expect(await authGuard(routeTo('/login', { guestOnly: true }))).toEqual({ name: 'dashboard' })
  })

  it('libera a rota protegida para quem está logado', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Ana', email: 'a@k.test', email_verified: true, created_at: null }
    auth.loaded = true

    expect(await authGuard(routeTo('/', { requiresAuth: true }))).toBe(true)
  })
})
