<script setup lang="ts">
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { submitting, message, fieldErrors, submit } = useApiForm()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })

async function onSubmit() {
  const ok = await submit(async () => {
    await auth.register(form)
    return true
  })

  if (ok) {
    await router.push('/')
  }
}
</script>

<template>
  <AuthLayout title="Criar conta" subtitle="Comece a organizar clientes, horas e cobranças.">
    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
      <FormAlert v-if="Object.keys(fieldErrors).length === 0" :message="message" />
      <BaseInput id="name" v-model="form.name" label="Nome" autocomplete="name" :error="fieldErrors.name" />
      <BaseInput id="email" v-model="form.email" label="E-mail" type="email" autocomplete="email" :error="fieldErrors.email" />
      <BaseInput
        id="password"
        v-model="form.password"
        label="Senha"
        type="password"
        autocomplete="new-password"
        :error="fieldErrors.password"
      />
      <p class="-mt-2 text-xs text-slate-500">Mínimo de 8 caracteres, com maiúscula, minúscula e número.</p>
      <BaseInput
        id="password_confirmation"
        v-model="form.password_confirmation"
        label="Confirmar senha"
        type="password"
        autocomplete="new-password"
      />
      <BaseButton :loading="submitting">Criar conta</BaseButton>
    </form>

    <template #footer>
      Já tem conta?
      <RouterLink to="/login" class="font-medium text-brand-600 hover:underline">Entrar</RouterLink>
    </template>
  </AuthLayout>
</template>
