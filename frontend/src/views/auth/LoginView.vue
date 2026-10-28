<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { safeRedirect } from '@/composables/safeRedirect'
import { useApiForm } from '@/composables/useApiForm'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { submitting, message, fieldErrors, submit } = useApiForm()

const form = reactive({ email: '', password: '', remember: false })
const verified = route.query.verificado === '1'
// Senha certa numa conta com login em duas etapas: falta o código do aplicativo.
const askCode = ref(false)
const code = ref('')

async function onSubmit() {
  const ok = await submit(async () => {
    if (askCode.value) {
      await auth.confirmLogin(code.value)
      return true
    }
    askCode.value = await auth.login(form)
    return !askCode.value
  })

  if (ok) {
    await router.push(safeRedirect(route.query.redirect))
  }
}

function restart() {
  askCode.value = false
  code.value = ''
  message.value = null
}
</script>

<template>
  <AuthLayout
    :title="askCode ? 'Código de verificação' : 'Entrar'"
    :subtitle="askCode ? 'Digite o código de 6 dígitos do seu aplicativo autenticador.' : 'Acesse sua conta do Kairo.'"
  >
    <form v-if="askCode" class="space-y-4" novalidate @submit.prevent="onSubmit">
      <FormAlert :message="message" />
      <BaseInput id="code" v-model="code" label="Código" autocomplete="one-time-code" :error="fieldErrors.code" />
      <p class="text-xs text-slate-500">Sem acesso ao aplicativo? Use um dos códigos de recuperação que você guardou.</p>
      <BaseButton :loading="submitting">Confirmar</BaseButton>
      <button type="button" class="w-full text-sm text-brand-600 hover:underline" @click="restart">Voltar</button>
    </form>

    <form v-else class="space-y-4" novalidate @submit.prevent="onSubmit">
      <FormAlert v-if="verified && !message" message="E-mail confirmado! Agora é só entrar." variant="success" />
      <FormAlert :message="message" />
      <BaseInput id="email" v-model="form.email" label="E-mail" type="email" autocomplete="email" :error="fieldErrors.email" />
      <BaseInput
        id="password"
        v-model="form.password"
        label="Senha"
        type="password"
        autocomplete="current-password"
        :error="fieldErrors.password"
      />
      <div class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2 text-slate-600">
          <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" />
          Lembrar de mim
        </label>
        <RouterLink to="/esqueci-a-senha" class="font-medium text-brand-600 hover:underline">Esqueci a senha</RouterLink>
      </div>
      <BaseButton :loading="submitting">Entrar</BaseButton>
    </form>

    <template v-if="!askCode" #footer>
      Ainda não tem conta?
      <RouterLink :to="{ name: 'register', query: route.query }" class="font-medium text-brand-600 hover:underline">Criar conta</RouterLink>
    </template>
  </AuthLayout>
</template>
