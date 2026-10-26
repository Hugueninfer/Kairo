<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
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
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Clientes</h1>
      <div v-if="canManage" class="w-40">
        <BaseButton type="button" @click="open(null)">Novo cliente</BaseButton>
      </div>
    </div>

    <input
      v-model="search"
      type="search"
      placeholder="Buscar por nome, e-mail ou documento"
      aria-label="Buscar clientes"
      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
      data-test="client-search"
    />

    <FormAlert :message="loadError" />

    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <p v-if="page && page.data.length === 0" class="p-6 text-sm text-slate-500">Nenhum cliente encontrado.</p>
      <ul class="divide-y divide-slate-100" data-test="clients">
        <li v-for="client in page?.data ?? []" :key="client.id" class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 text-sm">
          <div>
            <p class="font-medium">{{ client.name }}</p>
            <p class="text-slate-500">
              {{ [client.email, client.phone].filter(Boolean).join(' · ') || 'Sem contato' }}
            </p>
          </div>
          <div class="flex items-center gap-4">
            <RouterLink :to="{ name: 'projects', query: { cliente: client.id } }" class="text-slate-600 hover:text-brand-600">
              {{ client.projects_count ?? 0 }} {{ client.projects_count === 1 ? 'projeto' : 'projetos' }}
            </RouterLink>
            <template v-if="canManage">
              <button type="button" class="text-brand-600 hover:underline" @click="open(client)">Editar</button>
              <button type="button" class="text-red-600 hover:underline" @click="remove(client)">Apagar</button>
            </template>
          </div>
        </li>
      </ul>
    </section>

    <nav v-if="page && page.meta.last_page > 1" class="flex items-center justify-center gap-4 text-sm">
      <button type="button" :disabled="page.meta.current_page === 1" class="disabled:opacity-40" @click="goTo(page.meta.current_page - 1)">Anterior</button>
      <span>Página {{ page.meta.current_page }} de {{ page.meta.last_page }}</span>
      <button type="button" :disabled="page.meta.current_page === page.meta.last_page" class="disabled:opacity-40" @click="goTo(page.meta.current_page + 1)">
        Próxima
      </button>
    </nav>

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
          <label for="client-notes" class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
          <textarea id="client-notes" v-model="form.notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        </div>
        <BaseButton :loading="save.submitting.value">Salvar</BaseButton>
      </form>
    </BaseModal>
  </AppLayout>
</template>
