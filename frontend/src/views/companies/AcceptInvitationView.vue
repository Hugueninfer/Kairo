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
        <p class="text-fg-muted">Você está logado como {{ auth.user?.email }}.</p>
        <BaseButton type="button" :loading="submitting" @click="accept">Aceitar convite</BaseButton>
      </template>
      <template v-else>
        <p class="text-fg-muted">Entre ou crie sua conta com o e-mail convidado para aceitar.</p>
        <div class="flex gap-3">
          <RouterLink
            :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="btn-primary flex-1 text-center"
          >
            Entrar
          </RouterLink>
          <RouterLink
            :to="{ name: 'register', query: { redirect: route.fullPath } }"
            class="btn-secondary flex-1 text-center font-semibold text-fg-soft"
          >
            Criar conta
          </RouterLink>
        </div>
      </template>
    </div>
  </AuthLayout>
</template>
