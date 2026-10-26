import { createRouter, createWebHistory, type RouteLocationNormalized, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    requiresCompany?: boolean
    /** Permissão da empresa ativa exigida para abrir a tela. */
    permission?: string
    guestOnly?: boolean
  }
}

export const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true, requiresCompany: true },
  },
  {
    path: '/empresa',
    name: 'company-settings',
    component: () => import('@/views/companies/CompanySettingsView.vue'),
    meta: { requiresAuth: true, requiresCompany: true },
  },
  {
    path: '/empresa/papeis',
    name: 'company-roles',
    component: () => import('@/views/companies/RolesView.vue'),
    meta: { requiresAuth: true, requiresCompany: true, permission: 'roles.manage' },
  },
  {
    path: '/empresas/nova',
    name: 'company-create',
    component: () => import('@/views/companies/CreateCompanyView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/convite/:token',
    name: 'invitation',
    component: () => import('@/views/companies/AcceptInvitationView.vue'),
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/cadastro',
    name: 'register',
    component: () => import('@/views/auth/RegisterView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/esqueci-a-senha',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/redefinir-senha',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { guestOnly: true },
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

/**
 * Protege as rotas: visitante vai para o login; logado não vê telas de visitante;
 * telas da empresa exigem uma empresa ativa (senão, vai criar a primeira) e, quando
 * pedem, uma permissão do papel do usuário.
 */
export async function authGuard(to: RouteLocationNormalized) {
  const auth = useAuthStore()

  if (!auth.loaded) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresCompany) {
    const company = useCompanyStore()

    if (!company.loaded) {
      await company.load().catch(() => undefined)
    }

    if (!company.current) {
      return { name: 'company-create' }
    }

    if (to.meta.permission && !company.can(to.meta.permission)) {
      return { name: 'dashboard' }
    }
  }

  return true
}

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(authGuard)
