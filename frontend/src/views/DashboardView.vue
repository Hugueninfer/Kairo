<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { formatMoney } from '@/composables/money'
import { formatMinutes } from '@/composables/time'
import AppLayout from '@/layouts/AppLayout.vue'
import { authApi } from '@/services/auth'
import { toApiError } from '@/services/http'
import { tasksApi } from '@/services/projects'
import { reportsApi } from '@/services/reports'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'
import type { Dashboard, Task } from '@/types/api'

const auth = useAuthStore()
const company = useCompanyStore()
const notice = ref<string | null>(null)
const myTasks = ref<Task[] | null>(null)
const dashboard = ref<Dashboard | null>(null)

const money = (value: string) => formatMoney(value, company.current?.currency)
const hours = (minutes: number) => `${formatMinutes(minutes)} h`

/** Barras da receita: altura proporcional ao maior mês (mínimo visível para meses com valor). */
const revenueBars = computed(() => {
  const months = dashboard.value?.finance?.revenue ?? []
  const max = Math.max(...months.map((month) => Number(month.total)), 0)
  return months.map((month) => ({ ...month, height: max === 0 || Number(month.total) === 0 ? 0 : Math.max(2, (Number(month.total) / max) * 100) }))
})

function isLate(task: Task): boolean {
  return task.due_on !== null && task.due_on < new Date().toISOString().slice(0, 10)
}

onMounted(async () => {
  const [tasks, overview] = await Promise.all([tasksApi.mine().catch(() => []), reportsApi.dashboard().catch(() => null)])
  myTasks.value = tasks
  dashboard.value = overview
})

async function resend() {
  try {
    notice.value = await authApi.resendVerification()
  } catch (error) {
    notice.value = toApiError(error).message
  }
}
</script>

<template>
  <AppLayout>
    <div
      v-if="auth.user && !auth.user.email_verified"
      class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800"
      data-test="verify-banner"
    >
      <span>Confirme seu e-mail para liberar todos os recursos.</span>
      <button type="button" class="font-semibold underline" @click="resend">Reenviar e-mail</button>
    </div>
    <FormAlert :message="notice" variant="success" />

    <h1 class="text-2xl font-semibold">Olá, {{ auth.user?.name }}!</h1>
    <p class="text-slate-600">
      Você está em <strong>{{ company.current?.name }}</strong>.
    </p>

    <template v-if="dashboard">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="stats">
        <template v-if="dashboard.finance">
          <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm text-slate-500">Recebido em {{ dashboard.month.split(' ')[0]?.toLowerCase() }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums" data-test="received">{{ money(dashboard.finance.received_this_month) }}</p>
          </div>
          <RouterLink to="/faturas" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 hover:ring-brand-300">
            <p class="text-sm text-slate-500">A receber</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ money(dashboard.finance.open_total) }}</p>
            <p class="text-xs text-slate-500">{{ dashboard.finance.open_count }} {{ dashboard.finance.open_count === 1 ? 'fatura em aberto' : 'faturas em aberto' }}</p>
          </RouterLink>
          <RouterLink to="/faturas" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 hover:ring-brand-300" data-test="overdue">
            <p class="text-sm text-slate-500">Vencido</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums" :class="dashboard.finance.overdue_count ? 'text-red-700' : ''">
              {{ money(dashboard.finance.overdue_total) }}
            </p>
            <p class="text-xs" :class="dashboard.finance.overdue_count ? 'text-red-700' : 'text-slate-500'">
              <span v-if="dashboard.finance.overdue_count" aria-hidden="true">⚠ </span>{{ dashboard.finance.overdue_count }}
              {{ dashboard.finance.overdue_count === 1 ? 'fatura vencida' : 'faturas vencidas' }}
            </p>
          </RouterLink>
        </template>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200" data-test="my-hours">
          <p class="text-sm text-slate-500">Minhas horas no mês</p>
          <p class="mt-1 text-2xl font-semibold tabular-nums">{{ hours(dashboard.hours.mine.total) }}</p>
          <p class="text-xs text-slate-500">{{ hours(dashboard.hours.mine.billable) }} faturáveis</p>
        </div>
        <div v-if="dashboard.hours.team" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
          <p class="text-sm text-slate-500">Horas da equipe no mês</p>
          <p class="mt-1 text-2xl font-semibold tabular-nums">{{ hours(dashboard.hours.team.total) }}</p>
          <p class="text-xs text-slate-500">{{ hours(dashboard.hours.team.billable) }} faturáveis</p>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <section v-if="dashboard.finance" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200" data-test="revenue">
          <h2 class="font-semibold">Recebido por mês</h2>
          <p class="mb-4 text-sm text-slate-500">Últimos 6 meses</p>
          <div class="flex h-40 items-end gap-3" role="img" :aria-label="revenueBars.map((month) => `${month.label}: ${money(month.total)}`).join('; ')">
            <div v-for="month in revenueBars" :key="month.month" class="group relative flex h-full flex-1 flex-col justify-end">
              <span
                class="pointer-events-none absolute -top-1 left-1/2 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded bg-slate-800 px-2 py-1 text-xs text-white opacity-0 group-hover:opacity-100"
                >{{ money(month.total) }}</span
              >
              <div class="w-full rounded-t bg-brand-500 group-hover:bg-brand-600" :style="{ height: `${month.height}%` }" />
            </div>
          </div>
          <div class="mt-2 flex gap-3 border-t border-slate-200 pt-2 text-center text-xs text-slate-500">
            <span v-for="month in revenueBars" :key="month.month" class="flex-1">{{ month.label }}</span>
          </div>
        </section>

        <section v-if="dashboard.projects_over_budget" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200" data-test="over-budget">
          <h2 class="font-semibold">Projetos perto do limite do orçamento</h2>
          <p class="mb-4 text-sm text-slate-500">Horas lançadas × valor/hora, a partir de 80% do orçamento.</p>
          <p v-if="dashboard.projects_over_budget.length === 0" class="text-sm text-slate-500">Nenhum projeto perto do limite.</p>
          <ul class="space-y-3">
            <li v-for="project in dashboard.projects_over_budget" :key="project.id">
              <div class="flex items-baseline justify-between gap-2 text-sm">
                <RouterLink :to="{ name: 'project-board', params: { id: project.id } }" class="font-medium hover:text-brand-600">{{ project.name }}</RouterLink>
                <span class="tabular-nums" :class="project.percent >= 100 ? 'font-semibold text-red-700' : 'text-amber-700'">
                  {{ project.percent >= 100 ? 'Estourou · ' : '' }}{{ project.percent }}%
                </span>
              </div>
              <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full" :class="project.percent >= 100 ? 'bg-red-600' : 'bg-amber-500'" :style="{ width: `${Math.min(100, project.percent)}%` }" />
              </div>
              <p class="mt-0.5 text-xs text-slate-500">{{ money(project.consumed) }} de {{ money(project.budget) }}</p>
            </li>
          </ul>
        </section>
      </div>
    </template>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="mb-4 font-semibold">Minhas tarefas</h2>
      <p v-if="myTasks && myTasks.length === 0" class="text-sm text-slate-500">Nenhuma tarefa aberta com você. 🎉</p>
      <ul class="divide-y divide-slate-100" data-test="my-tasks">
        <li v-for="task in myTasks ?? []" :key="task.id" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
          <div>
            <RouterLink v-if="task.project" :to="{ name: 'project-board', params: { id: task.project.id } }" class="font-medium hover:text-brand-600">
              {{ task.title }}
            </RouterLink>
            <p class="text-slate-500">{{ task.project?.name }} · {{ task.status.label }}</p>
          </div>
          <span v-if="task.due_on" class="text-xs" :class="isLate(task) ? 'font-semibold text-red-600' : 'text-slate-500'">
            {{ isLate(task) ? 'Atrasada · ' : '' }}{{ new Date(`${task.due_on}T12:00:00`).toLocaleDateString('pt-BR') }}
          </span>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
