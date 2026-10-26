<script setup lang="ts">
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useCompanyStore } from '@/stores/company'

const company = useCompanyStore()
const router = useRouter()
const { submitting, message, fieldErrors, submit } = useApiForm()

const form = reactive({ name: '', document: '', default_hourly_rate: '' })

async function onSubmit() {
  const created = await submit(() =>
    company.create({
      name: form.name,
      document: form.document || null,
      default_hourly_rate: form.default_hourly_rate || null,
    }),
  )

  if (created) {
    await router.push('/')
  }
}
</script>

<template>
  <AuthLayout title="Crie sua empresa" subtitle="É nela que ficam seus clientes, projetos, horas e faturas. Você pode ter mais de uma.">
    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
      <FormAlert v-if="Object.keys(fieldErrors).length === 0" :message="message" />
      <BaseInput id="name" v-model="form.name" label="Nome da empresa" :error="fieldErrors.name" />
      <BaseInput id="document" v-model="form.document" label="CPF ou CNPJ (opcional)" :error="fieldErrors.document" />
      <BaseInput
        id="default_hourly_rate"
        v-model="form.default_hourly_rate"
        label="Valor da sua hora em R$ (opcional)"
        type="number"
        :error="fieldErrors.default_hourly_rate"
      />
      <BaseButton :loading="submitting">Criar empresa</BaseButton>
    </form>

    <template v-if="company.companies.length > 0" #footer>
      <RouterLink to="/" class="font-medium text-brand-600 hover:underline">Voltar</RouterLink>
    </template>
  </AuthLayout>
</template>
