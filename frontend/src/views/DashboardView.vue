<script setup lang="ts">
import { ref } from 'vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { authApi } from '@/services/auth'
import { toApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'

const auth = useAuthStore()
const company = useCompanyStore()
const notice = ref<string | null>(null)

async function resend() {
  try {
    notice.value = await authApi.resendVerification()
  } catch (error) {
    notice.value = toApiError(error).message
  }
}
</script>

<template>
  <AppLayout>
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
    <p class="text-slate-600">
      Você está em <strong>{{ company.current?.name }}</strong>. Em breve aqui: clientes, projetos, horas e faturas.
    </p>
  </AppLayout>
</template>
