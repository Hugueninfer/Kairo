<script setup lang="ts">
import QRCode from 'qrcode'
import { reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { useApiForm } from '@/composables/useApiForm'
import AppLayout from '@/layouts/AppLayout.vue'
import { authApi, type TwoFactorSetup } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const passwordForm = useApiForm()
const passwordSaved = ref<string | null>(null)
const password = reactive({ current_password: '', password: '', password_confirmation: '' })

async function changePassword() {
  passwordSaved.value = null
  const ok = await passwordForm.submit(async () => {
    await authApi.changePassword({ ...password })
    return true
  })
  if (ok) {
    Object.assign(password, { current_password: '', password: '', password_confirmation: '' })
    passwordSaved.value = 'Senha alterada.'
  }
}

// Login em duas etapas: senha → QR code → primeiro código → códigos de recuperação.
const twoFactor = useApiForm()
const confirmPassword = ref('')
const setup = ref<(TwoFactorSetup & { qr: string }) | null>(null)
const code = ref('')
const recoveryCodes = ref<string[] | null>(null)

async function startTwoFactor() {
  const started = await twoFactor.submit(() => authApi.startTwoFactor(confirmPassword.value))
  if (!started) return
  setup.value = { ...started, qr: await QRCode.toDataURL(started.otpauth_url, { margin: 1, width: 192 }) }
  confirmPassword.value = ''
}

async function confirmTwoFactor() {
  const codes = await twoFactor.submit(() => authApi.confirmTwoFactor(code.value.replace(/\s/g, '')))
  if (!codes) return
  recoveryCodes.value = codes
  setup.value = null
  code.value = ''
  if (auth.user) auth.user = { ...auth.user, two_factor_enabled: true }
}

async function regenerateCodes() {
  const codes = await twoFactor.submit(() => authApi.regenerateRecoveryCodes(confirmPassword.value))
  if (!codes) return
  recoveryCodes.value = codes
  confirmPassword.value = ''
}

async function disableTwoFactor() {
  if (!window.confirm('Desligar o login em duas etapas? Sua conta volta a ficar protegida só pela senha.')) return
  const ok = await twoFactor.submit(async () => {
    await authApi.disableTwoFactor(confirmPassword.value)
    return true
  })
  if (!ok) return
  confirmPassword.value = ''
  recoveryCodes.value = null
  if (auth.user) auth.user = { ...auth.user, two_factor_enabled: false }
}
</script>

<template>
  <AppLayout>
    <PageHeader title="Minha conta" :breadcrumbs="[{ label: 'Conta' }, { label: 'Segurança' }]">
      <template #subtitle>
        <span class="inline-flex items-center gap-2"><UserAvatar :name="auth.user?.name" :size="22" />{{ auth.user?.name }} · {{ auth.user?.email }}</span>
      </template>
    </PageHeader>

    <div class="grid items-start gap-4 xl:grid-cols-2">
    <section class="card p-6">
      <h2 class="card-title">Trocar senha</h2>
      <form class="mt-4 grid max-w-md gap-4" novalidate data-test="password-form" @submit.prevent="changePassword">
        <FormAlert :message="passwordForm.message.value" />
        <FormAlert :message="passwordSaved" variant="success" />
        <BaseInput
          id="current_password"
          v-model="password.current_password"
          label="Senha atual"
          type="password"
          autocomplete="current-password"
          :error="passwordForm.fieldErrors.value.current_password"
        />
        <BaseInput
          id="new_password"
          v-model="password.password"
          label="Nova senha"
          type="password"
          autocomplete="new-password"
          :error="passwordForm.fieldErrors.value.password"
        />
        <BaseInput
          id="new_password_confirmation"
          v-model="password.password_confirmation"
          label="Confirme a nova senha"
          type="password"
          autocomplete="new-password"
        />
        <BaseButton inline :loading="passwordForm.submitting.value">Salvar senha</BaseButton>
      </form>
    </section>

    <section class="card p-6" data-test="two-factor">
      <div class="flex flex-wrap items-center gap-2">
        <h2 class="card-title">Login em duas etapas</h2>
        <StatusBadge :tone="auth.user?.two_factor_enabled ? 'green' : 'neutral'" data-test="two-factor-status">{{
          auth.user?.two_factor_enabled ? 'Ligado' : 'Desligado'
        }}</StatusBadge>
      </div>
      <p class="mt-1 max-w-2xl text-sm text-fg-muted">
        Além da senha, o Kairo pede um código do aplicativo autenticador do seu celular (Google Authenticator, Authy, 1Password...). Recomendado para quem é
        dono ou administrador de uma empresa.
      </p>

      <div class="mt-4 max-w-md space-y-4">
        <FormAlert :message="twoFactor.message.value" />

        <div v-if="recoveryCodes" class="rounded-lg bg-amber-500/10 p-4 text-sm text-amber-200 ring-1 ring-amber-500/30" data-test="recovery-codes">
          <p class="font-semibold">Guarde estes códigos de recuperação</p>
          <p class="mt-1">Cada um entra uma vez se você perder o celular. Eles não aparecem de novo.</p>
          <ul class="mt-3 grid grid-cols-2 gap-1 font-mono">
            <li v-for="item in recoveryCodes" :key="item">{{ item }}</li>
          </ul>
        </div>

        <form v-if="setup" class="space-y-3" novalidate @submit.prevent="confirmTwoFactor">
          <p class="text-sm text-fg-soft">1. Escaneie o QR code no aplicativo autenticador.</p>
          <img :src="setup.qr" alt="QR code para o aplicativo autenticador" class="h-48 w-48 rounded-lg bg-white p-2 ring-1 ring-line" data-test="qr" />
          <p class="text-xs text-fg-muted">
            Não consegue escanear? Digite a chave: <code class="break-all font-mono text-fg-soft">{{ setup.secret }}</code>
          </p>
          <p class="text-sm text-fg-soft">2. Digite o código de 6 dígitos que aparece no aplicativo.</p>
          <BaseInput id="two_factor_code" v-model="code" label="Código" autocomplete="one-time-code" :error="twoFactor.fieldErrors.value.code" />
          <BaseButton inline :loading="twoFactor.submitting.value">Ligar</BaseButton>
        </form>

        <form v-else class="space-y-3" novalidate @submit.prevent="auth.user?.two_factor_enabled ? regenerateCodes() : startTwoFactor()">
          <BaseInput
            id="confirm_password"
            v-model="confirmPassword"
            label="Sua senha, para confirmar"
            type="password"
            autocomplete="current-password"
            :error="twoFactor.fieldErrors.value.password"
          />
          <div v-if="!auth.user?.two_factor_enabled" class="w-56">
            <BaseButton :loading="twoFactor.submitting.value">Configurar o aplicativo</BaseButton>
          </div>
          <div v-else class="flex flex-wrap gap-3">
            <BaseButton inline :loading="twoFactor.submitting.value">Gerar códigos novos</BaseButton>
            <button
              type="button"
              class="btn-danger"
              :disabled="twoFactor.submitting.value"
              data-test="disable-two-factor"
              @click="disableTwoFactor"
            >
              Desligar
            </button>
          </div>
        </form>
      </div>
    </section>
    </div>
  </AppLayout>
</template>
