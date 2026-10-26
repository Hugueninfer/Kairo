import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { RouteLocationNormalized } from 'vue-router'
import { authGuard } from '@/router'
import { authApi } from '@/services/auth'
import { companiesApi } from '@/services/companies'
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

  it('manda para criar a primeira empresa quando não há empresa ativa', async () => {
    vi.spyOn(companiesApi, 'list').mockResolvedValue([])
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Ana', email: 'a@k.test', email_verified: true, created_at: null }
    auth.loaded = true

    expect(await authGuard(routeTo('/', { requiresAuth: true, requiresCompany: true }))).toEqual({ name: 'company-create' })
  })

  it('não abre tela da empresa sem a permissão exigida', async () => {
    vi.spyOn(companiesApi, 'list').mockResolvedValue([
      {
        id: 'a',
        name: 'Alfa',
        document: null,
        currency: 'BRL',
        timezone: 'America/Sao_Paulo',
        default_hourly_rate: null,
        is_owner: false,
        is_current: true,
      },
    ])
    vi.spyOn(companiesApi, 'access').mockResolvedValue({ role: { id: 2, name: 'Membro' }, permissions: ['members.view'] })
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Ana', email: 'a@k.test', email_verified: true, created_at: null }
    auth.loaded = true
    const meta = { requiresAuth: true, requiresCompany: true }

    expect(await authGuard(routeTo('/empresa/papeis', { ...meta, permission: 'roles.manage' }))).toEqual({ name: 'dashboard' })
    expect(await authGuard(routeTo('/empresa', meta))).toBe(true)
  })
})
