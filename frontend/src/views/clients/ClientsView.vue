<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import UiPagination from '@/components/ui/UiPagination.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { useApiForm } from '@/composables/useApiForm'
import AppLayout from '@/layouts/AppLayout.vue'
import { clientsApi } from '@/services/clients'
import { toApiError } from '@/services/http'
import { useCompanyStore } from '@/stores/company'
import type { Client, Paginated } from '@/types/api'

const company = useCompanyStore()
const canManage = computed(() => company.can('clients.manage'))

const page = ref<Paginated<Client> | null>(null)
const search = ref('')
const currentPage = ref(1)
const loadError = ref<string | null>(null)

const editing = ref<Client | null>(null)
const showForm = ref(false)
const form = reactive({ name: '', email: '', phone: '', document: '', notes: '' })
const save = useApiForm()

async function load() {
  try {
    page.value = await clientsApi.list({ search: search.value || undefined, page: currentPage.value })
  } catch (error) {
    loadError.value = toApiError(error).message
  }
}

// Busca enquanto digita, com uma pequena espera para não chamar a API a cada tecla.
let timer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    currentPage.value = 1
    load()
  }, 300)
})

function open(client: Client | null) {
  editing.value = client
  Object.assign(form, {
    name: client?.name ?? '',
    email: client?.email ?? '',
    phone: client?.phone ?? '',
    document: client?.document ?? '',
    notes: client?.notes ?? '',
  })
  showForm.value = true
}

async function submit() {
  const payload = {
    name: form.name,
    email: form.email || null,
    phone: form.phone || null,
    document: form.document || null,
    notes: form.notes || null,
  }
  const saved = await save.submit(() => (editing.value ? clientsApi.update(editing.value.id, payload) : clientsApi.create(payload)))
  if (saved) {
    showForm.value = false
    await load()
  }
}

async function remove(client: Client) {
  if (!window.confirm(`Apagar o cliente ${client.name}? Os projetos dele continuam, sem cliente.`)) return
  try {
    await clientsApi.remove(client.id)
    await load()
  } catch (error) {
    loadError.value = toApiError(error).message
  }
}

function goTo(pageNumber: number) {
  currentPage.value = pageNumber
  load()
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <PageHeader title="Clientes" subtitle="Quem você atende, com contato e projetos." :breadcrumbs="[{ label: 'Comercial' }, { label: 'Clientes' }]">
      <template #actions>
        <BaseButton v-if="canManage" type="button" inline @click="open(null)"><AppIcon name="plus" :size="16" />Novo cliente</BaseButton>
      </template>
    </PageHeader>

    <FormAlert :message="loadError" />

    <section class="card overflow-hidden">
      <div class="card-header">
        <h2 class="card-title">
          Todos os clientes
          <span v-if="page" class="ml-1.5 text-sm font-normal text-fg-muted">{{ page.meta.total }}</span>
        </h2>
        <div class="relative w-full sm:w-72">
          <AppIcon name="search" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-fg-subtle" />
          <input
            v-model="search"
            type="search"
            placeholder="Buscar por nome, e-mail ou documento"
            aria-label="Buscar clientes"
            class="field field-sm w-full pl-9"
            data-test="client-search"
          />
        </div>
      </div>

      <EmptyState v-if="page && page.data.length === 0" icon="users" title="Nenhum cliente encontrado." />
      <div v-else class="overflow-x-auto">
        <table class="table-clean">
          <thead>
            <tr>
              <th>Cliente</th>
              <th class="hidden md:table-cell">Documento</th>
              <th>Projetos</th>
              <th v-if="canManage" class="text-right"><span class="sr-only">Ações</span></th>
            </tr>
          </thead>
          <tbody data-test="clients">
            <tr v-for="client in page?.data ?? []" :key="client.id">
              <td class="min-w-60">
                <div class="flex items-center gap-3">
                  <UserAvatar :name="client.name" :size="34" />
                  <div class="min-w-0">
                    <p class="truncate font-medium text-fg">{{ client.name }}</p>
                    <p class="truncate text-xs text-fg-muted">{{ [client.email, client.phone].filter(Boolean).join(' · ') || 'Sem contato' }}</p>
                  </div>
                </div>
              </td>
              <td class="hidden text-fg-muted md:table-cell">{{ client.document || '—' }}</td>
              <td>
                <RouterLink :to="{ name: 'projects', query: { cliente: client.id } }" class="inline-flex items-center gap-1.5 text-fg-soft hover:text-brand-300">
                  <AppIcon name="folder" :size="15" class="text-fg-subtle" />
                  {{ client.projects_count ?? 0 }} {{ client.projects_count === 1 ? 'projeto' : 'projetos' }}
                </RouterLink>
              </td>
              <td v-if="canManage" class="text-right whitespace-nowrap">
                <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-elevated hover:text-fg" :aria-label="`Editar ${client.name}`" title="Editar" @click="open(client)">
                  <AppIcon name="edit" :size="16" />
                </button>
                <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-red-500/10 hover:text-red-300" :aria-label="`Apagar ${client.name}`" title="Apagar" @click="remove(client)">
                  <AppIcon name="trash" :size="16" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <UiPagination v-if="page && page.meta.last_page > 1" :current="page.meta.current_page" :last="page.meta.last_page" @go="goTo" />
    </section>

    <BaseModal v-if="showForm" :title="editing ? 'Editar cliente' : 'Novo cliente'" @close="showForm = false">
      <form class="space-y-4" novalidate @submit.prevent="submit">
        <FormAlert v-if="Object.keys(save.fieldErrors.value).length === 0" :message="save.message.value" />
        <BaseInput id="client-name" v-model="form.name" label="Nome" :error="save.fieldErrors.value.name" />
        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput id="client-email" v-model="form.email" label="E-mail" type="email" :error="save.fieldErrors.value.email" />
          <BaseInput id="client-phone" v-model="form.phone" label="Telefone" :error="save.fieldErrors.value.phone" />
        </div>
        <BaseInput id="client-document" v-model="form.document" label="CPF ou CNPJ" :error="save.fieldErrors.value.document" />
        <div>
          <label for="client-notes" class="field-label">Observações</label>
          <textarea id="client-notes" v-model="form.notes" rows="3" class="field w-full" />
        </div>
        <BaseButton :loading="save.submitting.value">Salvar</BaseButton>
      </form>
    </BaseModal>
  </AppLayout>
</template>
