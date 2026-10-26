<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import FormAlert from '@/components/ui/FormAlert.vue'
import { authApi } from '@/services/auth'
import { toApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const notice = ref<string | null>(null)

async function resend() {
  try {
    notice.value = await authApi.resendVerification()
  } catch (error) {
    notice.value = toApiError(error).message
  }
}

async function logout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <div class="min-h-screen">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <span class="text-lg font-bold text-brand-600">Kairo</span>
        <div class="flex items-center gap-4 text-sm">
          <span class="text-slate-600">{{ auth.user?.name }}</span>
          <button type="button" class="font-medium text-slate-700 hover:text-brand-600" @click="logout">Sair</button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-4 px-4 py-8">
      <div
        v-if="auth.user && !auth.user.email_verified"
        class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800"
        data-test="verify-banner"
      >
        <span>Confirme seu e-mail para liberar todos os recursos.</span>
        <button type="button" class="font-semibold underline" @click="resend">Reenviar e-mail</button>
      </div>
      <FormAlert :message="notice" variant="success" />

      <h1 class="text-2xl font-semibold">Olá, {{ auth.user?.name }}!</h1>
      <p class="text-slate-600">Em breve aqui: clientes, projetos, horas e faturas.</p>
    </main>
  </div>
</template>
