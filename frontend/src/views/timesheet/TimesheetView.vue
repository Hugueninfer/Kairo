<script setup lang="ts">
import { computed, onMounted, reactive, ref, toRef, watch } from 'vue'
import TimeEntryModal from '@/components/timesheet/TimeEntryModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
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
const reviewable = computed(() => entries.value.filter((entry) => !entry.is_running && entry.status.value !== 'approved'))

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
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Planilha de horas</h1>
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <select v-if="canViewAll" v-model="viewing" aria-label="De quem" class="rounded-lg border border-slate-300 px-2 py-1" data-test="who">
          <option value="me">Minhas horas</option>
          <option value="all">Toda a equipe</option>
          <option v-for="member in members.filter((m) => m.id !== auth.user?.id)" :key="member.id" :value="member.id">{{ member.name }}</option>
        </select>
        <a :href="exportUrl" class="text-brand-600 hover:underline" data-test="export">Exportar CSV</a>
        <div v-if="isMine" class="w-36">
          <BaseButton type="button" @click="modal = { entry: null }">Lançar horas</BaseButton>
        </div>
      </div>
    </div>

    <form v-if="isMine && !timer.running" class="flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200" @submit.prevent="startTimer">
      <div class="min-w-48 flex-1">
        <label for="timer-project" class="mb-1 block text-xs font-medium text-slate-600">Projeto</label>
        <select id="timer-project" v-model="timerForm.project_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="timerForm.task_id = ''">
          <option value="" disabled>Escolha</option>
          <option v-for="project in picker.projects.value" :key="project.id" :value="project.id">{{ project.name }}</option>
        </select>
      </div>
      <div class="min-w-48 flex-1">
        <label for="timer-task" class="mb-1 block text-xs font-medium text-slate-600">Tarefa</label>
        <select id="timer-task" v-model="timerForm.task_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
          <option value="">Sem tarefa</option>
          <option v-for="task in picker.tasks.value" :key="task.id" :value="task.id">{{ task.title }}</option>
        </select>
      </div>
      <div class="min-w-48 flex-[2]">
        <label for="timer-description" class="mb-1 block text-xs font-medium text-slate-600">O que você vai fazer</label>
        <input id="timer-description" v-model="timerForm.description" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
      </div>
      <button type="submit" :disabled="!timerForm.project_id" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50" data-test="start-timer">
        ▶ Iniciar timer
      </button>
    </form>

    <FormAlert :message="error" />

    <section v-if="sheet" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <div class="mb-3 flex items-center justify-between">
        <button type="button" class="rounded px-2 py-1 hover:bg-slate-100" aria-label="Semana anterior" @click="week = addDays(sheet.week_start, -7)">‹</button>
        <p class="text-sm font-medium">Semana de {{ label(sheet.days[0]!) }} a {{ label(sheet.days[6]!) }}</p>
        <button type="button" class="rounded px-2 py-1 hover:bg-slate-100" aria-label="Próxima semana" @click="week = addDays(sheet.week_start, 7)">›</button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm" data-test="week-grid">
          <thead>
            <tr class="border-b border-slate-200 text-slate-500">
              <th class="py-2 text-left font-medium">Projeto / tarefa</th>
              <th v-for="(day, i) in sheet.days" :key="day" class="w-16 py-2 text-center font-medium">
                <button v-if="isMine" type="button" class="hover:text-brand-600" :title="`Lançar horas em ${label(day)}`" @click="modal = { entry: null, date: day }">
                  {{ weekdays[i] }}<br />{{ label(day) }}
                </button>
                <template v-else>{{ weekdays[i] }}<br />{{ label(day) }}</template>
              </th>
              <th class="w-20 py-2 text-right font-medium">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="sheet.rows.length === 0">
              <td :colspan="9" class="py-6 text-center text-slate-500">Nenhuma hora lançada nesta semana.</td>
            </tr>
            <tr v-for="row in sheet.rows" :key="row.project.id + (row.task?.id ?? '')" class="border-b border-slate-100">
              <td class="py-2">
                <p class="font-medium">{{ row.project.name }}</p>
                <p class="text-xs text-slate-500">{{ row.task?.title ?? 'Sem tarefa' }}</p>
              </td>
              <td v-for="(minutes, i) in row.minutes" :key="i" class="py-2 text-center tabular-nums" :class="minutes ? '' : 'text-slate-300'">
                {{ minutes ? formatMinutes(minutes) : '–' }}
              </td>
              <td class="py-2 text-right font-semibold tabular-nums">{{ formatMinutes(row.total) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="font-semibold">
              <td class="py-2">Total</td>
              <td v-for="(minutes, i) in sheet.totals.minutes" :key="i" class="py-2 text-center tabular-nums">{{ formatMinutes(minutes) }}</td>
              <td class="py-2 text-right tabular-nums" data-test="week-total">{{ formatMinutes(sheet.totals.total) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <p class="mt-2 text-right text-sm text-slate-600">
        Cobráveis: {{ formatMinutes(sheet.totals.billable_minutes) }} · <strong>{{ money(sheet.totals.amount) }}</strong>
      </p>
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold">Apontamentos da semana</h2>
        <div v-if="canApprove && selected.size" class="flex gap-3 text-sm">
          <button type="button" class="font-semibold text-emerald-700 hover:underline" data-test="approve" @click="review('approved')">Aprovar ({{ selected.size }})</button>
          <button type="button" class="font-semibold text-red-600 hover:underline" @click="review('rejected')">Recusar</button>
        </div>
      </div>
      <ul class="divide-y divide-slate-100 text-sm" data-test="entries">
        <li v-for="entry in entries" :key="entry.id" class="flex flex-wrap items-center gap-3 py-2">
          <input
            v-if="canApprove"
            type="checkbox"
            class="h-4 w-4 accent-brand-600"
            :disabled="!reviewable.includes(entry)"
            :checked="selected.has(entry.id)"
            :aria-label="`Selecionar ${entry.description ?? entry.project?.name}`"
            @change="toggle(entry.id)"
          />
          <span class="w-24 text-slate-500">{{ new Date(entry.started_at).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' }) }}</span>
          <div class="min-w-48 flex-1">
            <p class="font-medium">{{ entry.description || entry.task?.title || entry.project?.name }}</p>
            <p class="text-xs text-slate-500">
              {{ entry.project?.name }}<template v-if="entry.task"> · {{ entry.task.title }}</template>
              <template v-if="!isMine && entry.user"> · {{ entry.user.name }}</template>
            </p>
          </div>
          <span class="w-14 text-right font-semibold tabular-nums">{{ entry.is_running ? 'rodando' : formatMinutes(entry.duration_minutes) }}</span>
          <span
            class="rounded px-1.5 py-0.5 text-xs"
            :class="{
              'bg-amber-50 text-amber-700': entry.status.value === 'pending',
              'bg-emerald-50 text-emerald-700': entry.status.value === 'approved',
              'bg-red-50 text-red-700': entry.status.value === 'rejected',
            }"
          >
            {{ entry.status.label }}
          </span>
          <template v-if="entry.user?.id === auth.user?.id && !entry.is_running && entry.status.value !== 'approved'">
            <button type="button" class="text-brand-600 hover:underline" @click="modal = { entry }">Editar</button>
            <button type="button" class="text-red-600 hover:underline" @click="remove(entry)">Apagar</button>
          </template>
        </li>
      </ul>
    </section>

    <TimeEntryModal v-if="modal" :entry="modal.entry" :date="modal.date" @close="modal = null" @saved="onSaved" />
  </AppLayout>
</template>
