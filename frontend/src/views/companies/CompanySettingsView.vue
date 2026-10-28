<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { useApiForm } from '@/composables/useApiForm'
import AppLayout from '@/layouts/AppLayout.vue'
import { companiesApi } from '@/services/companies'
import { toApiError } from '@/services/http'
import { rolesApi } from '@/services/roles'
import { useCompanyStore } from '@/stores/company'
import type { Invitation, Member, Role } from '@/types/api'

const company = useCompanyStore()
const canEdit = computed(() => company.can('company.update'))
const canInvite = computed(() => company.can('members.invite'))
const canRemove = computed(() => company.can('members.remove'))
const canManageRoles = computed(() => company.can('roles.manage'))

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
const roles = ref<Role[]>([])
// O papel de dono é só do dono: não aparece para convite nem troca.
const assignableRoles = computed(() => roles.value.filter((role) => !role.is_locked))
const invite = useApiForm()
const inviteEmail = ref('')
const inviteRoleId = ref<number | null>(null)
const listError = ref<string | null>(null)

async function loadLists() {
  try {
    const [loadedMembers, loadedRoles, loadedInvitations] = await Promise.all([
      companiesApi.members(),
      rolesApi.list(),
      canInvite.value ? companiesApi.invitations() : Promise.resolve([]),
    ])
    members.value = loadedMembers
    roles.value = loadedRoles
    invitations.value = loadedInvitations
    inviteRoleId.value = loadedRoles.find((role) => role.system_key === 'member')?.id ?? null
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
  const created = await invite.submit(() => companiesApi.invite(inviteEmail.value, inviteRoleId.value))
  if (created) {
    invitations.value = [created, ...invitations.value.filter((item) => item.email !== created.email)]
    inviteEmail.value = ''
  }
}

async function revoke(invitation: Invitation) {
  await companiesApi.revokeInvitation(invitation.id)
  invitations.value = invitations.value.filter((item) => item.id !== invitation.id)
}

async function changeRole(member: Member, event: Event) {
  const roleId = Number((event.target as HTMLSelectElement).value)
  listError.value = null
  try {
    await companiesApi.changeMemberRole(member.id, roleId)
    const role = roles.value.find((item) => item.id === roleId)
    member.role = role ? { id: role.id, name: role.name } : null
  } catch (error) {
    listError.value = toApiError(error).message
  }
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
    <PageHeader :title="company.current?.name ?? 'Empresa'" subtitle="Dados da empresa, membros da equipe e convites." :breadcrumbs="[{ label: 'Empresa' }, { label: 'Configurações' }]" />

    <section class="card p-6">
      <h2 class="card-title mb-4">Dados da empresa</h2>
      <form class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="saveDetails">
        <div class="sm:col-span-2 space-y-2">
          <FormAlert v-if="Object.keys(details.fieldErrors.value).length === 0" :message="details.message.value" />
          <FormAlert :message="saved" variant="success" />
        </div>
        <BaseInput id="company-name" v-model="form.name" label="Nome" :error="details.fieldErrors.value.name" />
        <BaseInput id="company-document" v-model="form.document" label="CPF ou CNPJ" :error="details.fieldErrors.value.document" />
        <div>
          <label for="company-currency" class="field-label">Moeda</label>
          <select id="company-currency" v-model="form.currency" :disabled="!canEdit" class="field w-full">
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
        <div v-if="canEdit" class="flex sm:col-span-2">
          <BaseButton :loading="details.submitting.value" inline>Salvar alterações</BaseButton>
        </div>
      </form>
    </section>

    <section class="card p-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="card-title">Membros</h2>
        <RouterLink v-if="canManageRoles" to="/empresa/papeis" class="link inline-flex items-center gap-1.5 text-sm"><AppIcon name="shield" :size="15" />Papéis e permissões</RouterLink>
      </div>
      <FormAlert :message="listError" />
      <ul class="divide-y divide-line" data-test="members">
        <li v-for="member in members" :key="member.id" class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
          <div class="flex min-w-0 items-center gap-3">
            <UserAvatar :name="member.name" :size="36" />
            <div class="min-w-0">
              <p class="truncate font-medium text-fg">{{ member.name }}</p>
              <p class="truncate text-fg-muted">{{ member.email }}</p>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <select
              v-if="canManageRoles && !member.is_owner"
              :value="member.role?.id"
              :aria-label="`Papel de ${member.name}`"
              class="field field-sm w-40"
              data-test="member-role"
              @change="changeRole(member, $event)"
            >
              <option v-for="role in assignableRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
            </select>
            <StatusBadge v-else tone="blue">{{ member.role?.name ?? 'Sem papel' }}</StatusBadge>
            <button v-if="canRemove && !member.is_owner" type="button" class="btn-ghost btn-sm !text-red-400" @click="removeMember(member)">Remover</button>
          </div>
        </li>
      </ul>
    </section>

    <section v-if="canInvite" class="card p-6">
      <h2 class="card-title mb-4">Convidar pessoas</h2>
      <form class="flex flex-col gap-3 sm:flex-row sm:items-start" novalidate @submit.prevent="sendInvite">
        <div class="flex-1">
          <BaseInput id="invite-email" v-model="inviteEmail" label="E-mail" type="email" :error="invite.fieldErrors.value.email" />
        </div>
        <div class="sm:w-44">
          <label for="invite-role" class="field-label">Papel</label>
          <select id="invite-role" v-model="inviteRoleId" class="field w-full">
            <option v-for="role in assignableRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
          </select>
        </div>
        <div class="sm:mt-[26px] sm:w-40">
          <BaseButton :loading="invite.submitting.value">Convidar</BaseButton>
        </div>
      </form>
      <div class="mt-2">
        <FormAlert v-if="Object.keys(invite.fieldErrors.value).length === 0" :message="invite.message.value" />
      </div>

      <ul v-if="invitations.length" class="mt-4 divide-y divide-line" data-test="invitations">
        <li v-for="invitation in invitations" :key="invitation.id" class="flex items-center justify-between py-3 text-sm">
          <div>
            <p class="font-medium">{{ invitation.email }}</p>
            <p class="text-fg-muted">
              {{ invitation.role?.name ?? 'Membro' }} · {{ invitation.status.label }} · vence em
              {{ new Date(invitation.expires_at).toLocaleDateString('pt-BR') }}
            </p>
          </div>
          <button type="button" class="btn-ghost btn-sm !text-red-400" @click="revoke(invitation)">Cancelar</button>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
