<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useApiForm } from '@/composables/useApiForm'
import AppLayout from '@/layouts/AppLayout.vue'
import { toApiError } from '@/services/http'
import { rolesApi } from '@/services/roles'
import type { PermissionGroup, Role } from '@/types/api'

const catalog = ref<PermissionGroup[]>([])
const roles = ref<Role[]>([])
// Cópia editável das permissões de cada papel; comparada com o que veio da API para saber o que mudou.
const draft = ref<Record<number, Set<string>>>({})
const loadError = ref<string | null>(null)
const saveError = ref<string | null>(null)
const saved = ref<string | null>(null)
const saving = ref(false)

const createForm = useApiForm()
const newRoleName = ref('')

const changedRoles = computed(() => roles.value.filter((role) => !role.is_locked && isChanged(role)))

function isChanged(role: Role): boolean {
  const current = draft.value[role.id]
  if (!current) return false
  return current.size !== role.permissions.length || role.permissions.some((key) => !current.has(key))
}

function setDraft(list: Role[]) {
  draft.value = Object.fromEntries(list.map((role) => [role.id, new Set(role.permissions)]))
}

function hasPermission(role: Role, key: string): boolean {
  return role.is_locked || (draft.value[role.id]?.has(key) ?? false)
}

function toggle(role: Role, key: string) {
  if (role.is_locked) return
  const next = new Set(draft.value[role.id])
  if (next.has(key)) {
    next.delete(key)
  } else {
    next.add(key)
  }
  draft.value = { ...draft.value, [role.id]: next }
  saved.value = null
}

async function load() {
  try {
    const [loadedCatalog, loadedRoles] = await Promise.all([rolesApi.catalog(), rolesApi.list()])
    catalog.value = loadedCatalog
    roles.value = loadedRoles
    setDraft(loadedRoles)
  } catch (error) {
    loadError.value = toApiError(error).message
  }
}

async function saveChanges() {
  saving.value = true
  saveError.value = null
  saved.value = null
  try {
    const updated = await Promise.all(
      changedRoles.value.map((role) =>
        rolesApi.update(role.id, { name: role.name, permissions: [...(draft.value[role.id] ?? [])] }),
      ),
    )
    roles.value = roles.value.map((role) => updated.find((item) => item.id === role.id) ?? role)
    setDraft(roles.value)
    saved.value = 'Permissões salvas.'
  } catch (error) {
    saveError.value = toApiError(error).message
  } finally {
    saving.value = false
  }
}

async function createRole() {
  const created = await createForm.submit(() => rolesApi.create({ name: newRoleName.value, permissions: [] }))
  if (created) {
    roles.value = [...roles.value, created]
    draft.value = { ...draft.value, [created.id]: new Set(created.permissions) }
    newRoleName.value = ''
  }
}

async function removeRole(role: Role) {
  if (!window.confirm(`Apagar o papel ${role.name}?`)) return
  saveError.value = null
  try {
    await rolesApi.remove(role.id)
    roles.value = roles.value.filter((item) => item.id !== role.id)
  } catch (error) {
    saveError.value = toApiError(error).message
  }
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <div>
      <h1 class="text-2xl font-semibold">Papéis e permissões</h1>
      <p class="mt-1 text-sm text-slate-600">
        Cada pessoa tem um papel na empresa. Marque o que cada papel pode fazer. O dono sempre pode tudo.
      </p>
    </div>

    <FormAlert :message="loadError" />

    <section v-if="roles.length" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm" data-test="permission-matrix">
          <thead>
            <tr class="border-b border-slate-200 text-left">
              <th class="py-2 pr-4 font-medium text-slate-500">Permissão</th>
              <th v-for="role in roles" :key="role.id" class="px-2 py-2 text-center font-semibold">
                <span class="block">{{ role.name }}</span>
                <span class="block text-xs font-normal text-slate-500">
                  {{ role.members_count }} {{ role.members_count === 1 ? 'pessoa' : 'pessoas' }}
                </span>
                <button
                  v-if="!role.is_system"
                  type="button"
                  class="text-xs font-normal text-red-600 hover:underline"
                  @click="removeRole(role)"
                >
                  Apagar
                </button>
              </th>
            </tr>
          </thead>
          <tbody v-for="group in catalog" :key="group.group">
            <tr>
              <th :colspan="roles.length + 1" class="pt-4 pb-1 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ group.group }}
              </th>
            </tr>
            <tr v-for="permission in group.permissions" :key="permission.key" class="border-b border-slate-100">
              <td class="py-2 pr-4">{{ permission.label }}</td>
              <td v-for="role in roles" :key="role.id" class="px-2 py-2 text-center">
                <input
                  type="checkbox"
                  class="h-4 w-4 accent-brand-600"
                  :checked="hasPermission(role, permission.key)"
                  :disabled="role.is_locked"
                  :aria-label="`${role.name}: ${permission.label}`"
                  :data-test="`perm-${role.id}-${permission.key}`"
                  @change="toggle(role, permission.key)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-3">
        <div class="w-48">
          <BaseButton type="button" :loading="saving" :disabled="changedRoles.length === 0" data-test="save-permissions" @click="saveChanges">
            Salvar alterações
          </BaseButton>
        </div>
        <span v-if="changedRoles.length" class="text-sm text-slate-500">{{ changedRoles.length }} papel(is) com alterações</span>
      </div>
      <div class="mt-2 space-y-2">
        <FormAlert :message="saveError" />
        <FormAlert :message="saved" variant="success" />
      </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="mb-4 font-semibold">Novo papel</h2>
      <form class="flex flex-col gap-3 sm:flex-row sm:items-start" novalidate @submit.prevent="createRole">
        <div class="flex-1">
          <BaseInput id="role-name" v-model="newRoleName" label="Nome do papel" :error="createForm.fieldErrors.value.name" />
        </div>
        <div class="sm:mt-6 sm:w-40">
          <BaseButton :loading="createForm.submitting.value">Criar</BaseButton>
        </div>
      </form>
      <div class="mt-2">
        <FormAlert v-if="Object.keys(createForm.fieldErrors.value).length === 0" :message="createForm.message.value" />
      </div>
    </section>
  </AppLayout>
</template>
