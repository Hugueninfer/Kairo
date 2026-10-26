<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import AppLayout from '@/layouts/AppLayout.vue'
import { companiesApi } from '@/services/companies'
import { toApiError } from '@/services/http'
import { useCompanyStore } from '@/stores/company'
import type { Invitation, Member } from '@/types/api'

const company = useCompanyStore()
const isOwner = company.current?.is_owner ?? false

const details = useApiForm()
const saved = ref<string | null>(null)
const form = reactive({
  name: company.current?.name ?? '',
  document: company.current?.document ?? '',
  currency: company.current?.currency ?? 'BRL',
  default_hourly_rate: company.current?.default_hourly_rate ?? '',
})

const members = ref<Member[]>([])
const invitations = ref<Invitation[]>([])
const invite = useApiForm()
const inviteEmail = ref('')
const listError = ref<string | null>(null)

async function loadLists() {
  try {
    members.value = await companiesApi.members()
    invitations.value = isOwner ? await companiesApi.invitations() : []
  } catch (error) {
    listError.value = toApiError(error).message
  }
}

async function saveDetails() {
  saved.value = null
  const updated = await details.submit(() =>
    companiesApi.update({
      name: form.name,
      document: form.document || null,
      currency: form.currency,
      default_hourly_rate: form.default_hourly_rate || null,
    }),
  )
  if (updated) {
    company.replaceCurrent(updated)
    saved.value = 'Dados da empresa salvos.'
  }
}

async function sendInvite() {
  const created = await invite.submit(() => companiesApi.invite(inviteEmail.value))
  if (created) {
    invitations.value = [created, ...invitations.value.filter((item) => item.email !== created.email)]
    inviteEmail.value = ''
  }
}

async function revoke(invitation: Invitation) {
  await companiesApi.revokeInvitation(invitation.id)
  invitations.value = invitations.value.filter((item) => item.id !== invitation.id)
}

async function removeMember(member: Member) {
  if (!window.confirm(`Remover ${member.name} da empresa?`)) return
  try {
    await companiesApi.removeMember(member.id)
    members.value = members.value.filter((item) => item.id !== member.id)
  } catch (error) {
    listError.value = toApiError(error).message
  }
}

onMounted(loadLists)
</script>

<template>
  <AppLayout>
    <h1 class="text-2xl font-semibold">{{ company.current?.name }}</h1>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="mb-4 font-semibold">Dados da empresa</h2>
      <form class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="saveDetails">
        <div class="sm:col-span-2 space-y-2">
          <FormAlert v-if="Object.keys(details.fieldErrors.value).length === 0" :message="details.message.value" />
          <FormAlert :message="saved" variant="success" />
        </div>
        <BaseInput id="company-name" v-model="form.name" label="Nome" :error="details.fieldErrors.value.name" />
        <BaseInput id="company-document" v-model="form.document" label="CPF ou CNPJ" :error="details.fieldErrors.value.document" />
        <div>
          <label for="company-currency" class="mb-1 block text-sm font-medium text-slate-700">Moeda</label>
          <select id="company-currency" v-model="form.currency" :disabled="!isOwner" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="BRL">Real (R$)</option>
            <option value="USD">Dólar (US$)</option>
            <option value="EUR">Euro (€)</option>
          </select>
        </div>
        <BaseInput
          id="company-rate"
          v-model="form.default_hourly_rate"
          label="Valor/hora padrão"
          type="number"
          :error="details.fieldErrors.value.default_hourly_rate"
        />
        <div v-if="isOwner" class="sm:col-span-2 sm:w-48">
          <BaseButton :loading="details.submitting.value">Salvar</BaseButton>
        </div>
      </form>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="mb-4 font-semibold">Membros</h2>
      <FormAlert :message="listError" />
      <ul class="divide-y divide-slate-100" data-test="members">
        <li v-for="member in members" :key="member.id" class="flex items-center justify-between py-3 text-sm">
          <div>
            <p class="font-medium">{{ member.name }} <span v-if="member.is_owner" class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700">Dono</span></p>
            <p class="text-slate-500">{{ member.email }}</p>
          </div>
          <button v-if="isOwner && !member.is_owner" type="button" class="text-red-600 hover:underline" @click="removeMember(member)">Remover</button>
        </li>
      </ul>
    </section>

    <section v-if="isOwner" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="mb-4 font-semibold">Convidar pessoas</h2>
      <form class="flex flex-col gap-3 sm:flex-row sm:items-start" novalidate @submit.prevent="sendInvite">
        <div class="flex-1">
          <BaseInput id="invite-email" v-model="inviteEmail" label="E-mail" type="email" :error="invite.fieldErrors.value.email" />
        </div>
        <div class="sm:mt-6 sm:w-40">
          <BaseButton :loading="invite.submitting.value">Convidar</BaseButton>
        </div>
      </form>
      <div class="mt-2">
        <FormAlert v-if="Object.keys(invite.fieldErrors.value).length === 0" :message="invite.message.value" />
      </div>

      <ul v-if="invitations.length" class="mt-4 divide-y divide-slate-100" data-test="invitations">
        <li v-for="invitation in invitations" :key="invitation.id" class="flex items-center justify-between py-3 text-sm">
          <div>
            <p class="font-medium">{{ invitation.email }}</p>
            <p class="text-slate-500">{{ invitation.status.label }} · vence em {{ new Date(invitation.expires_at).toLocaleDateString('pt-BR') }}</p>
          </div>
          <button type="button" class="text-red-600 hover:underline" @click="revoke(invitation)">Cancelar</button>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
