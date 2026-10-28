<script setup lang="ts">
import { computed, onMounted, reactive, ref, toRef, watch } from 'vue'
import TimeEntryModal from '@/components/timesheet/TimeEntryModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { statusTone } from '@/composables/statusTone'
import { useProjectPicker } from '@/composables/useProjectPicker'
import { addDays, formatMinutes, isoDate } from '@/composables/time'
import AppLayout from '@/layouts/AppLayout.vue'
import { companiesApi } from '@/services/companies'
import { toApiError } from '@/services/http'
import { timesheetApi, type Who } from '@/services/timesheet'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'
import { useTimerStore } from '@/stores/timer'
import type { Member, TimeEntry, WeekSheet } from '@/types/api'

const auth = useAuthStore()
const company = useCompanyStore()
const timer = useTimerStore()
const canViewAll = computed(() => company.can('timesheet.view_all'))
const canApprove = computed(() => company.can('timesheet.approve'))

const week = ref(isoDate(new Date()))
// "me" = só as minhas horas; "all" = equipe toda; número = uma pessoa.
const viewing = ref<'me' | 'all' | number>('me')
const who = computed<Who>(() => (viewing.value === 'all' ? { all: true } : typeof viewing.value === 'number' ? { user: viewing.value } : {}))
const isMine = computed(() => viewing.value === 'me' || viewing.value === auth.user?.id)

const sheet = ref<WeekSheet | null>(null)
const entries = ref<TimeEntry[]>([])
const members = ref<Member[]>([])
const error = ref<string | null>(null)
const modal = ref<{ entry: TimeEntry | null; date?: string } | null>(null)
const selected = ref<Set<string>>(new Set())

const weekdays = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
const label = (day: string) => day.slice(8, 10) + '/' + day.slice(5, 7)
const money = (value: string) => Number(value).toLocaleString('pt-BR', { style: 'currency', currency: company.current?.currency ?? 'BRL' })

async function load() {
  error.value = null
  selected.value = new Set()
  try {
    sheet.value = await timesheetApi.week(week.value, who.value)
    entries.value = await timesheetApi.list(sheet.value.days[0]!, sheet.value.days[6]!, who.value)
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

const exportUrl = computed(() => (sheet.value ? timesheetApi.exportUrl(sheet.value.days[0]!, sheet.value.days[6]!, who.value) : '#'))
const reviewable = computed(() => entries.value.filter((entry) => !entry.is_running && !entry.is_invoiced && entry.status.value !== 'approved'))

function toggle(id: string) {
  const next = new Set(selected.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selected.value = next
}

async function review(status: 'approved' | 'rejected') {
  try {
    await timesheetApi.review([...selected.value], status)
    await load()
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

async function remove(entry: TimeEntry) {
  if (!window.confirm('Apagar este apontamento?')) return
  try {
    await timesheetApi.remove(entry.id)
    await load()
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

async function onSaved() {
  modal.value = null
  await load()
}

// Iniciar timer direto da planilha.
const timerForm = reactive({ project_id: '', task_id: '', description: '' })
const picker = useProjectPicker(toRef(timerForm, 'project_id'))

async function startTimer() {
  if (!timerForm.project_id) return
  try {
    await timer.start({ project_id: timerForm.project_id, task_id: timerForm.task_id || null, description: timerForm.description || null })
    timerForm.description = ''
    await load()
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

watch([week, viewing], load)
// Parar o timer no topo da tela também atualiza a planilha.
watch(() => timer.running?.id, (now, before) => {
  if (before && !now) load()
})

onMounted(async () => {
  await Promise.all([load(), picker.loadProjects().catch(() => undefined)])
  if (canViewAll.value) members.value = await companiesApi.members().catch(() => [])
})
</script>

<template>
  <AppLayout wide>
    <PageHeader title="Planilha de horas" subtitle="Seu tempo da semana, por projeto e por dia." :breadcrumbs="[{ label: 'Geral' }, { label: 'Horas' }]">
      <template #actions>
        <select v-if="canViewAll" v-model="viewing" aria-label="De quem" class="field field-sm w-48" data-test="who">
          <option value="me">Minhas horas</option>
          <option value="all">Toda a equipe</option>
          <option v-for="member in members.filter((m) => m.id !== auth.user?.id)" :key="member.id" :value="member.id">{{ member.name }}</option>
        </select>
        <a :href="exportUrl" class="btn-secondary" data-test="export"><AppIcon name="download" :size="16" />Exportar CSV</a>
        <BaseButton v-if="isMine" type="button" inline @click="modal = { entry: null }"><AppIcon name="plus" :size="16" />Lançar horas</BaseButton>
      </template>
    </PageHeader>

    <form v-if="isMine && !timer.running" class="card flex flex-wrap items-end gap-3 p-4" @submit.prevent="startTimer">
      <div class="min-w-48 flex-1">
        <label for="timer-project" class="field-label">Projeto</label>
        <select id="timer-project" v-model="timerForm.project_id" class="field field-sm w-full" @change="timerForm.task_id = ''">
          <option value="" disabled>Escolha</option>
          <option v-for="project in picker.projects.value" :key="project.id" :value="project.id">{{ project.name }}</option>
        </select>
      </div>
      <div class="min-w-48 flex-1">
        <label for="timer-task" class="field-label">Tarefa</label>
        <select id="timer-task" v-model="timerForm.task_id" class="field field-sm w-full">
          <option value="">Sem tarefa</option>
          <option v-for="task in picker.tasks.value" :key="task.id" :value="task.id">{{ task.title }}</option>
        </select>
      </div>
      <div class="min-w-48 flex-[2]">
        <label for="timer-description" class="field-label">O que você vai fazer</label>
        <input id="timer-description" v-model="timerForm.description" class="field field-sm w-full" placeholder="Ex.: ajustes no layout da home" />
      </div>
      <button type="submit" :disabled="!timerForm.project_id" class="btn-success w-full sm:w-auto" data-test="start-timer">
        <AppIcon name="play" :size="14" :stroke="2.5" />Iniciar timer
      </button>
    </form>

    <FormAlert :message="error" />

    <div v-if="sheet" class="grid gap-4 sm:grid-cols-3">
      <StatCard icon="clock" label="Total da semana" :value="`${formatMinutes(sheet.totals.total)} h`" />
      <StatCard icon="checkCircle" label="Cobráveis" :value="`${formatMinutes(sheet.totals.billable_minutes)} h`" />
      <StatCard icon="wallet" label="Valor cobrável" :value="money(sheet.totals.amount)" />
    </div>

    <section v-if="sheet" class="card overflow-hidden">
      <div class="card-header">
        <button type="button" class="btn-ghost btn-sm" aria-label="Semana anterior" @click="week = addDays(sheet.week_start, -7)"><AppIcon name="chevronLeft" :size="16" /></button>
        <p class="flex items-center gap-2 text-sm font-medium"><AppIcon name="calendar" :size="16" class="text-fg-muted" />Semana de {{ label(sheet.days[0]!) }} a {{ label(sheet.days[6]!) }}</p>
        <button type="button" class="btn-ghost btn-sm" aria-label="Próxima semana" @click="week = addDays(sheet.week_start, 7)"><AppIcon name="chevronRight" :size="16" /></button>
      </div>
      <div class="overflow-x-auto">
        <table class="table-clean min-w-[760px]" data-test="week-grid">
          <thead>
            <tr>
              <th>Projeto / tarefa</th>
              <th v-for="(day, i) in sheet.days" :key="day" class="w-16 !px-2 text-center">
                <button v-if="isMine" type="button" class="leading-tight hover:text-brand-300" :title="`Lançar horas em ${label(day)}`" @click="modal = { entry: null, date: day }">
                  {{ weekdays[i] }}<br /><span class="text-fg-subtle">{{ label(day) }}</span>
                </button>
                <template v-else>{{ weekdays[i] }}<br /><span class="text-fg-subtle">{{ label(day) }}</span></template>
              </th>
              <th class="w-20 text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="sheet.rows.length === 0">
              <td :colspan="9" class="py-8 text-center text-fg-muted">Nenhuma hora lançada nesta semana.</td>
            </tr>
            <tr v-for="row in sheet.rows" :key="row.project.id + (row.task?.id ?? '')">
              <td>
                <p class="font-medium text-fg">{{ row.project.name }}</p>
                <p class="text-xs text-fg-muted">{{ row.task?.title ?? 'Sem tarefa' }}</p>
              </td>
              <td v-for="(minutes, i) in row.minutes" :key="i" class="!px-2 text-center tabular-nums" :class="minutes ? 'text-fg' : 'text-fg-subtle'">
                <span :class="minutes ? 'rounded-md bg-brand-500/10 px-1.5 py-0.5 text-brand-200' : ''">{{ minutes ? formatMinutes(minutes) : '–' }}</span>
              </td>
              <td class="text-right font-semibold tabular-nums">{{ formatMinutes(row.total) }}</td>
            </tr>
          </tbody>
          <tfoot class="border-t border-line bg-surface-2/60 font-semibold">
            <tr>
              <td class="px-5 py-3">Total</td>
              <td v-for="(minutes, i) in sheet.totals.minutes" :key="i" class="px-2 py-3 text-center tabular-nums">{{ formatMinutes(minutes) }}</td>
              <td class="px-5 py-3 text-right tabular-nums" data-test="week-total">{{ formatMinutes(sheet.totals.total) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <section class="card overflow-hidden">
      <div class="card-header">
        <h2 class="card-title">Apontamentos da semana</h2>
        <div v-if="canApprove && selected.size" class="flex gap-2">
          <button type="button" class="btn-success btn-sm" data-test="approve" @click="review('approved')"><AppIcon name="check" :size="14" />Aprovar ({{ selected.size }})</button>
          <button type="button" class="btn-danger btn-sm" @click="review('rejected')">Recusar</button>
        </div>
      </div>
      <EmptyState v-if="entries.length === 0" icon="clock" title="Nenhum apontamento nesta semana." />
      <ul class="list-rows text-sm" data-test="entries">
        <li v-for="entry in entries" :key="entry.id" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-surface-2/50">
          <input
            v-if="canApprove"
            type="checkbox"
            class="h-4 w-4"
            :disabled="!reviewable.includes(entry)"
            :checked="selected.has(entry.id)"
            :aria-label="`Selecionar ${entry.description ?? entry.project?.name}`"
            @change="toggle(entry.id)"
          />
          <span class="w-24 text-xs text-fg-muted capitalize">{{ new Date(entry.started_at).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' }) }}</span>
          <div class="min-w-48 flex-1">
            <p class="font-medium text-fg">{{ entry.description || entry.task?.title || entry.project?.name }}</p>
            <p class="text-xs text-fg-muted">
              {{ entry.project?.name }}<template v-if="entry.task"> · {{ entry.task.title }}</template>
              <template v-if="!isMine && entry.user"> · {{ entry.user.name }}</template>
            </p>
          </div>
          <span class="w-16 text-right font-semibold tabular-nums">{{ entry.is_running ? 'rodando' : formatMinutes(entry.duration_minutes) }}</span>
          <StatusBadge :tone="statusTone('timeEntry', entry.status.value)">{{ entry.status.label }}</StatusBadge>
          <StatusBadge v-if="entry.is_invoiced" tone="neutral" :dot="false">Faturado</StatusBadge>
          <span v-if="entry.user?.id === auth.user?.id && !entry.is_running && entry.status.value !== 'approved'" class="flex">
            <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-elevated hover:text-fg" aria-label="Editar" title="Editar" @click="modal = { entry }"><AppIcon name="edit" :size="16" /></button>
            <button type="button" class="rounded-lg p-1.5 text-fg-muted hover:bg-red-500/10 hover:text-red-300" aria-label="Apagar" title="Apagar" @click="remove(entry)"><AppIcon name="trash" :size="16" /></button>
          </span>
        </li>
      </ul>
    </section>

    <TimeEntryModal v-if="modal" :entry="modal.entry" :date="modal.date" @close="modal = null" @saved="onSaved" />
  </AppLayout>
</template>
