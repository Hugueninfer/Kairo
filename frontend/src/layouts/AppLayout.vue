<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'

const auth = useAuthStore()
const company = useCompanyStore()
const router = useRouter()

async function onSwitch(event: Event) {
  const id = (event.target as HTMLSelectElement).value
  if (id === 'nova') {
    await router.push({ name: 'company-create' })
    return
  }
  await company.switchTo(id)
  // Recarrega a tela atual com os dados da nova empresa.
  await router.replace({ path: router.currentRoute.value.fullPath, force: true })
}

async function logout() {
  await auth.logout()
  company.reset()
  await router.push('/login')
}
</script>

<template>
  <div class="min-h-screen">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="flex items-center gap-6">
          <RouterLink to="/" class="text-lg font-bold text-brand-600">Kairo</RouterLink>
          <nav class="flex gap-4 text-sm text-slate-600">
            <RouterLink to="/" class="hover:text-brand-600" active-class="font-semibold text-brand-600" exact-active-class="font-semibold text-brand-600">Painel</RouterLink>
            <RouterLink to="/empresa" class="hover:text-brand-600" active-class="font-semibold text-brand-600">Empresa</RouterLink>
          </nav>
        </div>
        <div class="flex items-center gap-4 text-sm">
          <label class="sr-only" for="company-switcher">Empresa ativa</label>
          <select
            id="company-switcher"
            :value="company.current?.id"
            class="rounded-lg border border-slate-300 px-2 py-1 text-sm"
            data-test="company-switcher"
            @change="onSwitch"
          >
            <option v-for="item in company.companies" :key="item.id" :value="item.id">{{ item.name }}</option>
            <option value="nova">+ Nova empresa</option>
          </select>
          <span class="text-slate-600">{{ auth.user?.name }}</span>
          <button type="button" class="font-medium text-slate-700 hover:text-brand-600" @click="logout">Sair</button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-4 px-4 py-8">
      <slot />
    </main>
  </div>
</template>
