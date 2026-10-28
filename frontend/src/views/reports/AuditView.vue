<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { companiesApi } from '@/services/companies'
import { toApiError } from '@/services/http'
import { projectsApi } from '@/services/projects'
import { reportsApi } from '@/services/reports'
import { useCompanyStore } from '@/stores/company'
import type { AuditEntry, AuditSubject, Member, Option, Paginated } from '@/types/api'

const company = useCompanyStore()
const subjects = ref<Option<AuditSubject>[]>([])
const members = ref<Member[]>([])
const filters = reactive({ subject: '' as AuditSubject | '', user: '' as number | '', from: '', to: '' })
const page = ref<Paginated<AuditEntry> | null>(null)
const currentPage = ref(1)
const loading = ref(false)
const error = ref<string | null>(null)

const when = (value: string) =>
  new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })

async function load() {
  loading.value = true
  error.value = null
  try {
    page.value = await reportsApi.audit({
      subject: filters.subject || undefined,
      user: filters.user === '' ? undefined : filters.user,
      from: filters.from || undefined,
      to: filters.to || undefined,
      page: currentPage.value,
    })
  } catch (failure) {
    error.value = toApiError(failure).message
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  currentPage.value = 1
  void load()
}

function goTo(pageNumber: number) {
  currentPage.value = pageNumber
  void load()
}

onMounted(async () => {
  void load()
  const [options, team] = await Promise.allSettled([
    projectsApi.options(),
    company.can('members.view') ? companiesApi.members() : Promise.resolve([]),
  ])
  if (options.status === 'fulfilled') subjects.value = options.value.audit_subjects
  if (team.status === 'fulfilled') members.value = team.value
})
</script>

<template>
  <AppLayout>
    <div>
      <h1 class="text-2xl font-semibold">Histórico de alterações</h1>
      <p class="text-sm text-slate-600">Quem criou, alterou ou apagou o quê na empresa. Os registros ficam guardados por um ano.</p>
    </div>

    <form class="flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200" @submit.prevent="applyFilters">
      <label class="text-sm">
        <span class="mb-1 block text-slate-600">Tipo</span>
        <select v-model="filters.subject" class="rounded-lg border border-slate-300 px-2 py-1.5" data-test="filter-subject" @change="applyFilters">
          <option value="">Tudo</option>
          <option v-for="option in subjects" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>
      <label v-if="members.length" class="text-sm">
        <span class="mb-1 block text-slate-600">Pessoa</span>
        <select v-model="filters.user" class="rounded-lg border border-slate-300 px-2 py-1.5" data-test="filter-user" @change="applyFilters">
          <option value="">Todas</option>
          <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
        </select>
      </label>
      <label class="text-sm">
        <span class="mb-1 block text-slate-600">De</span>
        <input v-model="filters.from" type="date" class="rounded-lg border border-slate-300 px-2 py-1.5" @change="applyFilters" />
      </label>
      <label class="text-sm">
        <span class="mb-1 block text-slate-600">Até</span>
        <input v-model="filters.to" type="date" class="rounded-lg border border-slate-300 px-2 py-1.5" @change="applyFilters" />
      </label>
      <span v-if="loading" class="ml-auto text-sm text-slate-500">Carregando…</span>
    </form>

    <FormAlert :message="error" />

    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <p v-if="page && page.data.length === 0" class="p-6 text-sm text-slate-500">Nenhuma alteração encontrada com esses filtros.</p>
      <ol class="divide-y divide-slate-100" data-test="audit-list">
        <li v-for="entry in page?.data ?? []" :key="entry.id" class="px-6 py-4 text-sm">
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p>
              <span class="font-semibold">{{ entry.causer?.name ?? 'Sistema' }}</span>
              {{ entry.summary }}
            </p>
            <span class="text-xs text-slate-500">
              <span v-if="entry.subject" class="mr-2 rounded bg-slate-100 px-1.5 py-0.5 text-slate-600">{{ entry.subject.label }}</span>
              <time :datetime="entry.created_at">{{ when(entry.created_at) }}</time>
            </span>
          </div>
          <ul v-if="entry.changes.length" class="mt-2 space-y-0.5 text-slate-600">
            <li v-for="change in entry.changes" :key="change.field">
              <span class="text-slate-500">{{ change.label }}:</span>{{ ' ' }}
              <template v-if="change.old !== null">
                <del class="text-slate-400">{{ change.old }}</del>
                <span aria-hidden="true"> → </span>
                <span class="sr-only"> passou para </span>
              </template>
              <span class="text-slate-800">{{ change.new ?? '(vazio)' }}</span>
            </li>
          </ul>
        </li>
      </ol>
    </section>

    <nav v-if="page && page.meta.last_page > 1" class="flex items-center justify-center gap-4 text-sm">
      <button type="button" :disabled="page.meta.current_page === 1" class="disabled:opacity-40" @click="goTo(page.meta.current_page - 1)">Anterior</button>
      <span>Página {{ page.meta.current_page }} de {{ page.meta.last_page }}</span>
      <button type="button" :disabled="page.meta.current_page === page.meta.last_page" class="disabled:opacity-40" @click="goTo(page.meta.current_page + 1)">
        Próxima
      </button>
    </nav>
  </AppLayout>
</template>
