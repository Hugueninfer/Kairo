<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { companiesApi } from '@/services/companies'
import { toApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'
import type { InvitationPreview } from '@/types/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const company = useCompanyStore()
const token = String(route.params.token)

const preview = ref<InvitationPreview | null>(null)
const loadError = ref<string | null>(null)
const { submitting, message, submit } = useApiForm()

onMounted(async () => {
  try {
    preview.value = await companiesApi.previewInvitation(token)
  } catch (error) {
    loadError.value = toApiError(error).message
  }
})

async function accept() {
  const joined = await submit(() => companiesApi.acceptInvitation(token))
  if (joined) {
    await company.load()
    await router.push('/')
  }
}
</script>

<template>
  <AuthLayout title="Convite para o Kairo">
    <FormAlert :message="loadError" />

    <div v-if="preview" class="space-y-4 text-sm">
      <p>
        <strong>{{ preview.invited_by }}</strong> convidou <strong>{{ preview.email }}</strong> para fazer parte de
        <strong>{{ preview.company_name }}</strong>.
      </p>
      <FormAlert :message="message" />

      <template v-if="auth.isAuthenticated">
        <p class="text-slate-500">Você está logado como {{ auth.user?.email }}.</p>
        <BaseButton type="button" :loading="submitting" @click="accept">Aceitar convite</BaseButton>
      </template>
      <template v-else>
        <p class="text-slate-500">Entre ou crie sua conta com o e-mail convidado para aceitar.</p>
        <div class="flex gap-3">
          <RouterLink
            :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-center font-semibold text-white hover:bg-brand-700"
          >
            Entrar
          </RouterLink>
          <RouterLink
            :to="{ name: 'register', query: { redirect: route.fullPath } }"
            class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-center font-semibold text-slate-700 hover:bg-slate-50"
          >
            Criar conta
          </RouterLink>
        </div>
      </template>
    </div>
  </AuthLayout>
</template>
