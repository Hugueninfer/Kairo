<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'
import AppLogo from '@/components/ui/AppLogo.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'

/**
 * Menu lateral. "expanded" mostra sempre os rótulos (gaveta do celular); sem ele,
 * vira uma barra só de ícones entre md e lg e mostra os rótulos a partir de lg.
 */
const props = defineProps<{ expanded?: boolean }>()
const emit = defineEmits<{ navigate: []; logout: [] }>()

const auth = useAuthStore()
const company = useCompanyStore()
const route = useRoute()

interface Item {
  to: string
  label: string
  icon: IconName
  permission?: string
  /** Outras rotas que também deixam o item ativo. */
  match?: (path: string) => boolean
  exact?: boolean
}

const groups = computed(() =>
  (
    [
      {
        title: 'Geral',
        items: [
          { to: '/', label: 'Painel', icon: 'dashboard', exact: true },
          { to: '/projetos', label: 'Projetos', icon: 'folder', permission: 'projects.view' },
          { to: '/horas', label: 'Horas', icon: 'clock', permission: 'projects.view' },
          { to: '/boards', label: 'Boards', icon: 'board', permission: 'boards.view' },
        ],
      },
      {
        title: 'Comercial',
        items: [
          { to: '/clientes', label: 'Clientes', icon: 'users', permission: 'clients.view' },
          {
            to: '/orcamentos',
            label: 'Orçamentos',
            icon: 'file',
            permission: 'quotes.view',
            match: (path: string) => path.startsWith('/orcamentos') || path === '/contratos',
          },
          { to: '/faturas', label: 'Faturas', icon: 'receipt', permission: 'invoices.view' },
        ],
      },
      {
        title: 'Análise',
        items: [
          { to: '/relatorios', label: 'Relatórios', icon: 'chart', permission: 'reports.view' },
          { to: '/historico', label: 'Histórico', icon: 'history', permission: 'audit.view' },
        ],
      },
      {
        title: 'Empresa',
        items: [
          { to: '/empresa', label: 'Empresa', icon: 'building', exact: true },
          { to: '/empresa/papeis', label: 'Papéis', icon: 'shield', permission: 'roles.manage' },
        ],
      },
    ] as { title: string; items: Item[] }[]
  )
    .map((group) => ({ ...group, items: group.items.filter((item) => !item.permission || company.can(item.permission)) }))
    .filter((group) => group.items.length > 0),
)

function isActive(item: Item): boolean {
  if (item.match) return item.match(route.path)
  if (item.exact) return route.path === item.to
  return route.path === item.to || route.path.startsWith(`${item.to}/`)
}

// Classes que só valem no modo responsivo (barra de ícones entre md e lg).
const label = computed(() => (props.expanded ? '' : 'md:max-lg:sr-only'))
const center = computed(() => (props.expanded ? '' : 'md:max-lg:justify-center md:max-lg:px-0'))
</script>

<template>
  <div class="flex h-full flex-col">
    <div class="flex h-16 shrink-0 items-center px-5" :class="center">
      <RouterLink to="/" class="flex items-center" @click="emit('navigate')">
        <AppLogo :height="22" :class="expanded ? '' : 'md:max-lg:hidden'" />
        <AppLogo v-if="!expanded" variant="mark" :height="20" class="hidden md:max-lg:block" />
      </RouterLink>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Menu principal">
      <div v-for="group in groups" :key="group.title">
        <p class="mb-1.5 px-3 text-[11px] font-semibold tracking-wider text-fg-subtle uppercase" :class="expanded ? '' : 'md:max-lg:hidden'">
          {{ group.title }}
        </p>
        <div v-if="!expanded" class="mx-auto mb-2 hidden h-px w-6 bg-line md:max-lg:block" />
        <ul class="space-y-0.5">
          <li v-for="item in group.items" :key="item.to">
            <RouterLink
              :to="item.to"
              :title="item.label"
              class="relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
              :class="[
                center,
                isActive(item) ? 'bg-surface-2 font-medium text-fg' : 'text-fg-muted hover:bg-surface-2/60 hover:text-fg',
              ]"
              :aria-current="isActive(item) ? 'page' : undefined"
              @click="emit('navigate')"
            >
              <span v-if="isActive(item)" class="absolute top-1.5 bottom-1.5 -left-3 w-[3px] rounded-r-full bg-brand-500" aria-hidden="true" />
              <AppIcon :name="item.icon" :class="isActive(item) ? 'text-brand-400' : ''" />
              <span :class="label">{{ item.label }}</span>
            </RouterLink>
          </li>
        </ul>
      </div>
    </nav>

    <div class="shrink-0 border-t border-line p-3">
      <div class="flex items-center gap-2" :class="expanded ? '' : 'md:max-lg:flex-col'">
        <RouterLink
          to="/conta"
          class="flex min-w-0 flex-1 items-center gap-3 rounded-lg p-2 hover:bg-surface-2"
          :class="expanded ? '' : 'md:max-lg:justify-center'"
          active-class="bg-surface-2"
          title="Minha conta"
          @click="emit('navigate')"
        >
          <UserAvatar :name="auth.user?.name" :size="34" />
          <span class="min-w-0" :class="label">
            <span class="block truncate text-sm font-medium text-fg">{{ auth.user?.name }}</span>
            <span class="block truncate text-xs text-fg-muted">{{ auth.user?.email }}</span>
          </span>
        </RouterLink>
        <button type="button" class="rounded-lg p-2 text-fg-muted hover:bg-surface-2 hover:text-fg" aria-label="Sair" title="Sair" @click="emit('logout')">
          <AppIcon name="logout" />
        </button>
      </div>
    </div>
  </div>
</template>
