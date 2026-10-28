<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SidebarNav from '@/components/layout/SidebarNav.vue'
import TimerWidget from '@/components/timesheet/TimerWidget.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppLogo from '@/components/ui/AppLogo.vue'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'
import { useTimerStore } from '@/stores/timer'

defineProps<{
  /** Telas largas, como o quadro de tarefas. */
  wide?: boolean
  /** Ocupa a largura toda, como o canvas dos boards. */
  full?: boolean
}>()

const auth = useAuthStore()
const company = useCompanyStore()
const timer = useTimerStore()
const router = useRouter()
const route = useRoute()

// Gaveta do menu no celular.
const drawer = ref(false)
watch(() => route.fullPath, () => (drawer.value = false))

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') drawer.value = false
}

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

async function onSwitch(event: Event) {
  const id = (event.target as HTMLSelectElement).value
  if (id === 'nova') {
    await router.push({ name: 'company-create' })
    return
  }
  await company.switchTo(id)
  await timer.load(true)
  // Recarrega a tela atual com os dados da nova empresa.
  await router.replace({ path: router.currentRoute.value.fullPath, force: true })
}

async function logout() {
  await auth.logout()
  company.reset()
  timer.reset()
  await router.push('/login')
}
</script>

<template>
  <div class="min-h-screen md:pl-[76px] lg:pl-64">
    <!-- Menu fixo: barra de ícones no tablet, completo no desktop. -->
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-[76px] border-r border-line bg-surface md:block lg:w-64">
      <SidebarNav @logout="logout" />
    </aside>

    <!-- Gaveta do celular. -->
    <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity" leave-active-class="transition-opacity">
      <div v-if="drawer" class="fixed inset-0 z-40 bg-black/70 md:hidden" @click="drawer = false" />
    </Transition>
    <Transition enter-from-class="-translate-x-full" leave-to-class="-translate-x-full" enter-active-class="transition-transform" leave-active-class="transition-transform">
      <aside v-if="drawer" class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] border-r border-line bg-surface md:hidden">
        <button type="button" class="absolute top-4 right-3 rounded-lg p-1.5 text-fg-muted hover:bg-surface-2" aria-label="Fechar menu" @click="drawer = false">
          <AppIcon name="close" />
        </button>
        <SidebarNav expanded @navigate="drawer = false" @logout="logout" />
      </aside>
    </Transition>

    <div class="flex min-h-screen min-w-0 flex-col">
      <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-canvas/85 px-4 backdrop-blur sm:px-6">
        <button type="button" class="-ml-1 rounded-lg p-2 text-fg-muted hover:bg-surface-2 hover:text-fg md:hidden" aria-label="Abrir menu" @click="drawer = true">
          <AppIcon name="menu" />
        </button>
        <RouterLink to="/" class="md:hidden" aria-label="Início">
          <AppLogo :height="18" />
        </RouterLink>

        <div class="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
          <TimerWidget v-if="company.can('projects.view')" />
          <div class="relative">
            <AppIcon name="building" :size="16" class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-fg-muted" />
            <label class="sr-only" for="company-switcher">Empresa ativa</label>
            <select
              id="company-switcher"
              :value="company.current?.id"
              class="field field-sm max-w-36 pl-8 sm:max-w-56"
              data-test="company-switcher"
              @change="onSwitch"
            >
              <option v-for="item in company.companies" :key="item.id" :value="item.id">{{ item.name }}</option>
              <option value="nova">+ Nova empresa</option>
            </select>
          </div>
        </div>
      </header>

      <main class="mx-auto w-full flex-1 space-y-6 px-4 sm:px-6" :class="full ? 'py-4' : ['py-6 sm:py-8', wide ? 'max-w-[1400px]' : 'max-w-7xl']">
        <slot />
      </main>
    </div>
  </div>
</template>
