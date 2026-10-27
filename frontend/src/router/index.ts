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
    path: '/clientes',
    name: 'clients',
    component: () => import('@/views/clients/ClientsView.vue'),
    meta: { requiresAuth: true, requiresCompany: true, permission: 'clients.view' },
  },
  {
    path: '/projetos',
    name: 'projects',
    component: () => import('@/views/projects/ProjectsView.vue'),
    meta: { requiresAuth: true, requiresCompany: true, permission: 'projects.view' },
  },
  {
    path: '/projetos/:id',
    name: 'project-board',
    component: () => import('@/views/projects/ProjectBoardView.vue'),
    props: true,
    meta: { requiresAuth: true, requiresCompany: true, permission: 'projects.view' },
  },
  {
    path: '/horas',
    name: 'timesheet',
    component: () => import('@/views/timesheet/TimesheetView.vue'),
    meta: { requiresAuth: true, requiresCompany: true, permission: 'projects.view' },
  },
  {
    path: '/orcamentos',
    name: 'quotes',
    component: () => import('@/views/quotes/QuotesView.vue'),
    meta: { requiresAuth: true, requiresCompany: true, permission: 'quotes.view' },
  },
  {
    path: '/orcamentos/novo',
    name: 'quote-new',
    component: () => import('@/views/quotes/QuoteEditorView.vue'),
    meta: { requiresAuth: true, requiresCompany: true, permission: 'quotes.manage' },
  },
  {
    path: '/orcamentos/:id',
    name: 'quote-edit',
    component: () => import('@/views/quotes/QuoteEditorView.vue'),
    meta: { requiresAuth: true, requiresCompany: true, permission: 'quotes.view' },
  },
  {
    path: '/contratos',
    name: 'contracts',
    component: () => import('@/views/quotes/ContractsView.vue'),
    meta: { requiresAuth: true, requiresCompany: true, permission: 'quotes.view' },
  },
  {
    // Link do e-mail do cliente: público, a API confere a assinatura.
    path: '/orcamento/:id',
    name: 'public-quote',
    component: () => import('@/views/quotes/PublicQuoteView.vue'),
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
