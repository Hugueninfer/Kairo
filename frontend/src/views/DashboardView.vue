<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'
import AreaChart from '@/components/ui/AreaChart.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import RadialGauge from '@/components/ui/RadialGauge.vue'
import StatCard from '@/components/ui/StatCard.vue'
import StatStrip from '@/components/ui/StatStrip.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatMoney } from '@/composables/money'
import { statusTone } from '@/composables/statusTone'
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

const revenue = computed(() => dashboard.value?.finance?.revenue ?? [])
const revenueLabel = computed(() => revenue.value.map((month) => `${month.label}: ${money(month.total)}`).join('; '))

/** Valores do eixo do gráfico, sem centavos e abreviados (R$ 12 mil). */
const compactMoney = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: company.current?.currency ?? 'BRL', notation: 'compact', maximumFractionDigits: 1 }).format(value)

/** Parte faturável das minhas horas no mês, para o medidor. */
const billablePercent = computed(() => {
  const mine = dashboard.value?.hours.mine
  return mine && mine.total > 0 ? Math.round((mine.billable / mine.total) * 100) : 0
})

/** Atalhos do painel, conforme o que o papel da pessoa pode fazer. */
const shortcuts = computed(() =>
  (
    [
      { to: '/projetos', label: 'Projeto', hint: 'Abrir um novo projeto', icon: 'folder', permission: 'projects.manage' },
      { to: '/orcamentos/novo', label: 'Orçamento', hint: 'Montar uma proposta', icon: 'file', permission: 'quotes.manage' },
      { to: '/faturas/nova', label: 'Fatura', hint: 'Cobrar um cliente', icon: 'receipt', permission: 'invoices.manage' },
      { to: '/horas', label: 'Horas', hint: 'Lançar o tempo de hoje', icon: 'clock', permission: 'projects.view' },
    ] as { to: string; label: string; hint: string; icon: IconName; permission: string }[]
  )
    .filter((item) => company.can(item.permission))
    .slice(0, 3),
)

const plural = (count: number, one: string, many: string) => `${count} ${count === 1 ? one : many}`

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
      class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"
      data-test="verify-banner"
    >
      <span class="flex items-center gap-2"><AppIcon name="mail" :size="16" /> Confirme seu e-mail para liberar todos os recursos.</span>
      <button type="button" class="font-semibold text-amber-100 underline-offset-4 hover:underline" @click="resend">Reenviar e-mail</button>
    </div>
    <FormAlert :message="notice" variant="success" />

    <PageHeader :title="`Olá, ${auth.user?.name}!`">
      <template #subtitle>
        Você está em <strong class="font-medium text-fg-soft">{{ company.current?.name }}</strong>.
      </template>
      <template #actions>
        <RouterLink v-if="company.can('projects.view')" to="/horas" class="btn-secondary"><AppIcon name="clock" :size="16" /> Lançar horas</RouterLink>
        <RouterLink v-if="company.can('invoices.manage')" to="/faturas/nova" class="btn-primary"><AppIcon name="plus" :size="16" /> Nova fatura</RouterLink>
      </template>
    </PageHeader>

    <template v-if="dashboard">
      <StatStrip :count="(dashboard.finance ? 3 : 0) + 1 + (dashboard.hours.team ? 1 : 0)" data-test="stats">
        <template v-if="dashboard.finance">
          <StatCard
            plain
            :label="`Recebido em ${dashboard.month.split(' ')[0]?.toLowerCase()}`"
            :value="money(dashboard.finance.received_this_month)"
            :badge="{ text: 'Mês', tone: 'green', icon: 'trendingUp' }"
            hint="Pagamentos confirmados"
            data-test="received"
          />
          <StatCard
            plain
            to="/faturas"
            label="A receber"
            :value="money(dashboard.finance.open_total)"
            :badge="{ text: 'Aberto', tone: 'blue' }"
            :hint="plural(dashboard.finance.open_count, 'fatura em aberto', 'faturas em aberto')"
          />
          <StatCard
            plain
            to="/faturas"
            label="Vencido"
            :value="money(dashboard.finance.overdue_total)"
            :value-class="dashboard.finance.overdue_count ? 'text-red-400' : ''"
            :badge="dashboard.finance.overdue_count ? { text: 'Atraso', tone: 'red', icon: 'trendingDown' } : { text: 'Em dia', tone: 'green', icon: 'check' }"
            data-test="overdue"
          >
            <template #hint>
              <span :class="dashboard.finance.overdue_count ? 'text-red-400' : ''">
                {{ plural(dashboard.finance.overdue_count, 'fatura vencida', 'faturas vencidas') }}
              </span>
            </template>
          </StatCard>
        </template>
        <StatCard
            plain
          label="Minhas horas no mês"
          :value="hours(dashboard.hours.mine.total)"
          :hint="`${hours(dashboard.hours.mine.billable)} faturáveis`"
          :badge="{ text: `${billablePercent}% fat.`, tone: billablePercent >= 70 ? 'green' : 'neutral' }"
          data-test="my-hours"
        />
        <StatCard
            plain
          v-if="dashboard.hours.team"
          label="Horas da equipe no mês"
          :value="hours(dashboard.hours.team.total)"
          :hint="`${hours(dashboard.hours.team.billable)} faturáveis`"
        />
      </StatStrip>

      <div class="grid gap-4 lg:grid-cols-3">
        <div v-if="shortcuts.length" class="grid content-start gap-3" :class="dashboard.finance ? '' : 'lg:col-span-1'">
          <RouterLink
            v-for="item in shortcuts"
            :key="item.to"
            :to="item.to"
            class="card group flex items-center gap-4 p-4 transition-colors hover:border-line-strong hover:bg-surface-2/50"
          >
            <span class="grid h-11 w-11 place-items-center rounded-lg bg-elevated text-fg"><AppIcon :name="item.icon" :size="20" /></span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-fg">{{ item.label }}</span>
              <span class="block text-xs text-fg-muted">{{ item.hint }}</span>
            </span>
            <AppIcon name="chevronRight" class="text-fg-subtle transition-transform group-hover:translate-x-0.5 group-hover:text-fg" />
          </RouterLink>
        </div>

        <section v-if="dashboard.finance" class="card p-5 lg:col-span-2" :class="shortcuts.length ? '' : 'lg:col-span-3'" data-test="revenue">
          <div class="mb-5 flex flex-wrap items-start justify-between gap-2">
            <div>
              <h2 class="card-title">Recebido por mês</h2>
              <p class="text-xs text-fg-muted">Últimos 6 meses</p>
            </div>
            <span class="flex items-center gap-1.5 text-xs text-fg-muted"><span class="h-2 w-2 rounded-full bg-brand-500" /> Recebido</span>
          </div>
          <AreaChart
            :labels="revenue.map((month) => month.label)"
            :series="[{ name: 'Recebido', values: revenue.map((month) => Number(month.total)) }]"
            :format="compactMoney"
            :description="revenueLabel"
            :height="190"
          />
        </section>

        <section v-else-if="dashboard.hours.mine.total > 0" class="card p-5 lg:col-span-2">
          <h2 class="card-title">Horas faturáveis</h2>
          <p class="mb-4 text-xs text-fg-muted">Parte do seu tempo no mês que vira cobrança.</p>
          <RadialGauge :percent="billablePercent" label="Faturável" />
        </section>
      </div>
    </template>

    <div class="grid gap-4 lg:grid-cols-3">
      <section class="card overflow-hidden" :class="dashboard?.projects_over_budget ? 'lg:col-span-2' : 'lg:col-span-3'">
        <div class="card-header">
          <h2 class="card-title">Minhas tarefas</h2>
          <span v-if="myTasks?.length" class="chip">{{ myTasks.length }} abertas</span>
        </div>
        <EmptyState v-if="myTasks && myTasks.length === 0" icon="checkCircle" title="Nenhuma tarefa aberta com você. 🎉" />
        <div v-else class="overflow-x-auto">
          <table class="table-clean" data-test="my-tasks">
            <thead>
              <tr>
                <th>Tarefa</th>
                <th>Situação</th>
                <th class="hidden text-right sm:table-cell">Prazo</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="task in myTasks ?? []" :key="task.id">
                <td class="min-w-56">
                  <RouterLink v-if="task.project" :to="{ name: 'project-board', params: { id: task.project.id } }" class="font-medium text-fg hover:text-brand-300">
                    {{ task.title }}
                  </RouterLink>
                  <span v-else class="font-medium">{{ task.title }}</span>
                  <p class="text-xs text-fg-muted">
                    {{ task.project?.name }}
                    <span v-if="task.due_on" class="sm:hidden" :class="isLate(task) ? 'text-red-400' : ''">· {{ new Date(`${task.due_on}T12:00:00`).toLocaleDateString('pt-BR') }}</span>
                  </p>
                </td>
                <td><StatusBadge :tone="statusTone('task', task.status.value)">{{ task.status.label }}</StatusBadge></td>
                <td class="hidden text-right whitespace-nowrap sm:table-cell">
                  <span v-if="task.due_on" class="text-xs" :class="isLate(task) ? 'font-semibold text-red-400' : 'text-fg-muted'">
                    {{ isLate(task) ? 'Atrasada · ' : '' }}{{ new Date(`${task.due_on}T12:00:00`).toLocaleDateString('pt-BR') }}
                  </span>
                  <span v-else class="text-xs text-fg-subtle">Sem prazo</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="dashboard?.projects_over_budget" class="card p-5" data-test="over-budget">
        <h2 class="card-title">Projetos perto do limite</h2>
        <p class="mb-5 text-xs text-fg-muted">Horas lançadas × valor/hora, a partir de 80% do orçamento.</p>
        <EmptyState v-if="dashboard.projects_over_budget.length === 0" icon="target" title="Nenhum projeto perto do limite." />
        <ul class="space-y-5">
          <li v-for="project in dashboard.projects_over_budget" :key="project.id">
            <div class="flex items-baseline justify-between gap-2 text-sm">
              <RouterLink :to="{ name: 'project-board', params: { id: project.id } }" class="truncate font-medium hover:text-brand-300">{{ project.name }}</RouterLink>
              <span class="text-xs tabular-nums" :class="project.percent >= 100 ? 'font-semibold text-red-400' : 'text-amber-400'">
                {{ project.percent >= 100 ? 'Estourou · ' : '' }}{{ project.percent }}%
              </span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-elevated">
              <div class="h-full rounded-full" :class="project.percent >= 100 ? 'bg-red-500' : 'bg-amber-400'" :style="{ width: `${Math.min(100, project.percent)}%` }" />
            </div>
            <p class="mt-1.5 text-xs text-fg-muted">{{ money(project.consumed) }} de {{ money(project.budget) }}</p>
          </li>
        </ul>
      </section>
    </div>
  </AppLayout>
</template>
