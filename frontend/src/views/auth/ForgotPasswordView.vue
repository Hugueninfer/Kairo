<script setup lang="ts">
import { ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { authApi } from '@/services/auth'

const { submitting, message, fieldErrors, submit } = useApiForm()
const email = ref('')
const sent = ref<string | null>(null)

async function onSubmit() {
  const result = await submit(() => authApi.forgotPassword(email.value))
  if (result) {
    sent.value = result
  }
}
</script>

<template>
  <AuthLayout title="Esqueci a senha" subtitle="Informe seu e-mail e enviaremos um link para criar uma nova senha.">
    <FormAlert v-if="sent" :message="sent" variant="success" />
    <form v-else class="space-y-4" novalidate @submit.prevent="onSubmit">
      <FormAlert v-if="Object.keys(fieldErrors).length === 0" :message="message" />
      <BaseInput id="email" v-model="email" label="E-mail" type="email" autocomplete="email" :error="fieldErrors.email" />
      <BaseButton :loading="submitting">Enviar link</BaseButton>
    </form>

    <template #footer>
      <RouterLink to="/login" class="link">Voltar para o login</RouterLink>
    </template>
  </AuthLayout>
</template>
