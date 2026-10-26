<script setup lang="ts">
import { reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { authApi } from '@/services/auth'

const route = useRoute()
const router = useRouter()
const { submitting, message, fieldErrors, submit } = useApiForm()

const form = reactive({
  token: String(route.query.token ?? ''),
  email: String(route.query.email ?? ''),
  password: '',
  password_confirmation: '',
})

async function onSubmit() {
  const result = await submit(() => authApi.resetPassword(form))
  if (result) {
    await router.push('/login')
  }
}
</script>

<template>
  <AuthLayout title="Criar nova senha">
    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
      <FormAlert v-if="Object.keys(fieldErrors).length === 0" :message="message" />
      <BaseInput id="email" v-model="form.email" label="E-mail" type="email" autocomplete="email" :error="fieldErrors.email" />
      <BaseInput
        id="password"
        v-model="form.password"
        label="Nova senha"
        type="password"
        autocomplete="new-password"
        :error="fieldErrors.password"
      />
      <BaseInput
        id="password_confirmation"
        v-model="form.password_confirmation"
        label="Confirmar nova senha"
        type="password"
        autocomplete="new-password"
      />
      <BaseButton :loading="submitting">Salvar nova senha</BaseButton>
    </form>
  </AuthLayout>
</template>
