<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import UiPagination from '@/components/ui/UiPagination.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
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
    <PageHeader
      title="Histórico de alterações"
      subtitle="Quem criou, alterou ou apagou o quê na empresa. Os registros ficam guardados por um ano."
      :breadcrumbs="[{ label: 'Análise' }, { label: 'Histórico' }]"
    />

    <form class="card flex flex-wrap items-end gap-3 p-4" @submit.prevent="applyFilters">
      <label class="text-sm">
        <span class="field-label">Tipo</span>
        <select v-model="filters.subject" class="field field-sm w-44" data-test="filter-subject" @change="applyFilters">
          <option value="">Tudo</option>
          <option v-for="option in subjects" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>
      <label v-if="members.length" class="text-sm">
        <span class="field-label">Pessoa</span>
        <select v-model="filters.user" class="field field-sm w-44" data-test="filter-user" @change="applyFilters">
          <option value="">Todas</option>
          <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
        </select>
      </label>
      <label class="text-sm">
        <span class="field-label">De</span>
        <input v-model="filters.from" type="date" class="field field-sm" @change="applyFilters" />
      </label>
      <label class="text-sm">
        <span class="field-label">Até</span>
        <input v-model="filters.to" type="date" class="field field-sm" @change="applyFilters" />
      </label>
      <span v-if="loading" class="ml-auto flex items-center gap-2 text-sm text-fg-muted"><AppIcon name="refresh" :size="15" class="animate-spin" />Carregando…</span>
    </form>

    <FormAlert :message="error" />

    <section class="card overflow-hidden">
      <EmptyState v-if="page && page.data.length === 0" icon="history" title="Nenhuma alteração encontrada com esses filtros." />
      <ol class="list-rows" data-test="audit-list">
        <li v-for="entry in page?.data ?? []" :key="entry.id" class="flex gap-3 px-5 py-4 text-sm">
          <UserAvatar :name="entry.causer?.name ?? 'Sistema'" :size="32" />
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <p class="text-fg-soft">
                <span class="font-semibold text-fg">{{ entry.causer?.name ?? 'Sistema' }}</span>
                {{ entry.summary }}
              </p>
              <span class="flex items-center gap-2 text-xs text-fg-muted">
                <StatusBadge v-if="entry.subject" tone="neutral" :dot="false">{{ entry.subject.label }}</StatusBadge>
                <time :datetime="entry.created_at">{{ when(entry.created_at) }}</time>
              </span>
            </div>
            <ul v-if="entry.changes.length" class="mt-2 space-y-1 rounded-lg border border-line bg-surface-2/60 px-3 py-2 text-[13px] text-fg-muted">
              <li v-for="change in entry.changes" :key="change.field">
                <span class="text-fg-muted">{{ change.label }}:</span>{{ ' ' }}
                <template v-if="change.old !== null">
                  <del class="text-red-300/70">{{ change.old }}</del>
                  <span aria-hidden="true"> → </span>
                  <span class="sr-only"> passou para </span>
                </template>
                <span class="text-emerald-300">{{ change.new ?? '(vazio)' }}</span>
              </li>
            </ul>
          </div>
        </li>
      </ol>
      <UiPagination v-if="page && page.meta.last_page > 1" :current="page.meta.current_page" :last="page.meta.last_page" @go="goTo" />
    </section>
  </AppLayout>
</template>
