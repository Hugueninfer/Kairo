<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatCard from '@/components/ui/StatCard.vue'
import { formatMoney } from '@/composables/money'
import { formatMinutes, isoDate } from '@/composables/time'
import AppLayout from '@/layouts/AppLayout.vue'
import { toApiError } from '@/services/http'
import { reportsApi } from '@/services/reports'
import { useCompanyStore } from '@/stores/company'
import type { HoursReport, ReportGroup } from '@/types/api'

// O relatório é calculado na fila: enquanto a API responde "processando", pergunta de novo.
const POLL_EVERY_MS = 1000
const MAX_POLLS = 30

const company = useCompanyStore()
const groups: { value: ReportGroup; label: string; column: string }[] = [
  { value: 'project', label: 'Por projeto', column: 'Projeto' },
  { value: 'member', label: 'Por pessoa', column: 'Pessoa' },
  { value: 'client', label: 'Por cliente', column: 'Cliente' },
]

const today = new Date()
const filters = reactive({
  from: isoDate(new Date(today.getFullYear(), today.getMonth(), 1)),
  to: isoDate(new Date(today.getFullYear(), today.getMonth() + 1, 0)),
  group: 'project' as ReportGroup,
})
const report = ref<HoursReport | null>(null)
const processing = ref(false)
const error = ref<string | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined
let request = 0

const money = (value: string) => formatMoney(value, company.current?.currency)
const column = computed(() => groups.find((group) => group.value === filters.group)?.column ?? '')
const billableShare = computed(() => {
  const totals = report.value?.totals
  return totals && totals.minutes > 0 ? Math.round((totals.billable_minutes / totals.minutes) * 100) : 0
})
const maxMinutes = computed(() => Math.max(...(report.value?.rows ?? []).map((row) => row.minutes), 0))

function setMonth(offset: number) {
  const start = new Date(today.getFullYear(), today.getMonth() + offset, 1)
  filters.from = isoDate(start)
  filters.to = isoDate(new Date(start.getFullYear(), start.getMonth() + 1, 0))
  void load()
}

async function load(poll = 0) {
  clearTimeout(timer)
  const current = poll === 0 ? ++request : request
  error.value = null
  processing.value = true
  try {
    const result = await reportsApi.hours({ ...filters })
    if (current !== request) return
    if (result.status === 'ready' && result.data) {
      report.value = result.data
      processing.value = false
    } else if (poll < MAX_POLLS) {
      timer = setTimeout(() => void load(poll + 1), POLL_EVERY_MS)
    } else {
      processing.value = false
      error.value = 'O relatório está demorando mais que o normal. Tente de novo em instantes.'
    }
  } catch (failure) {
    if (current !== request) return
    processing.value = false
    error.value = toApiError(failure).message
  }
}

function chooseGroup(group: ReportGroup) {
  filters.group = group
  void load()
}

/** Texto que começa com =, +, -, @ viraria fórmula na planilha (CSV injection); números ficam como estão. */
function safeCell(value: string): string {
  return /^-?\d+(,\d+)?$/.test(value) || !/^[=+\-@\t\r]/.test(value) ? value : `'${value}`
}

/** CSV com ponto e vírgula, que o Excel em português abre direto nas colunas certas. */
function downloadCsv() {
  if (!report.value) return
  const decimal = (value: number) => value.toFixed(2).replace('.', ',')
  const lines = [
    [column.value, 'Horas', 'Horas faturáveis', 'Valor'],
    ...report.value.rows.map((row) => [row.label, decimal(row.minutes / 60), decimal(row.billable_minutes / 60), decimal(Number(row.amount))]),
    ['Total', decimal(report.value.totals.minutes / 60), decimal(report.value.totals.billable_minutes / 60), decimal(Number(report.value.totals.amount))],
  ]
  const csv = lines.map((line) => line.map((cell) => `"${safeCell(String(cell)).replaceAll('"', '""')}"`).join(';')).join('\r\n')
  const url = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `horas-${filters.group}-${filters.from}-a-${filters.to}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(() => load())
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <AppLayout wide>
    <PageHeader
      title="Relatório de horas"
      subtitle="Horas lançadas no período (as recusadas ficam de fora), com o valor das faturáveis."
      :breadcrumbs="[{ label: 'Análise' }, { label: 'Relatórios' }]"
    >
      <template #actions>
        <button type="button" class="btn-secondary" :disabled="!report || processing" data-test="download-csv" @click="downloadCsv">
          <AppIcon name="download" :size="16" />Baixar CSV
        </button>
      </template>
    </PageHeader>

    <div class="card flex flex-wrap items-end gap-4 p-4">
      <div class="flex rounded-lg border border-line bg-surface-2 p-0.5" role="tablist" aria-label="Agrupar por">
        <button
          v-for="group in groups"
          :key="group.value"
          type="button"
          role="tab"
          class="rounded-md px-3 py-1.5 text-sm transition-colors"
          :class="filters.group === group.value ? 'bg-elevated font-medium text-fg shadow' : 'text-fg-muted hover:text-fg'"
          :aria-selected="filters.group === group.value"
          :data-group="group.value"
          @click="chooseGroup(group.value)"
        >
          {{ group.label }}
        </button>
      </div>
      <label class="text-sm">
        <span class="field-label">De</span>
        <input v-model="filters.from" type="date" class="field field-sm" data-test="from" @change="load()" />
      </label>
      <label class="text-sm">
        <span class="field-label">Até</span>
        <input v-model="filters.to" type="date" class="field field-sm" data-test="to" @change="load()" />
      </label>
      <div class="flex gap-1">
        <button type="button" class="btn-ghost btn-sm" @click="setMonth(0)">Este mês</button>
        <button type="button" class="btn-ghost btn-sm" @click="setMonth(-1)">Mês passado</button>
      </div>
      <span v-if="processing" class="ml-auto flex items-center gap-2 text-sm text-fg-muted" data-test="processing">
        <AppIcon name="refresh" :size="15" class="animate-spin" /> Calculando…
      </span>
    </div>

    <FormAlert :message="error" />

    <div v-if="report" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" :class="{ 'opacity-60': processing }">
      <StatCard icon="clock" label="Horas no período" :value="formatMinutes(report.totals.minutes)" />
      <StatCard icon="checkCircle" label="Horas faturáveis" :value="formatMinutes(report.totals.billable_minutes)" :badge="{ text: `${billableShare}%`, tone: billableShare >= 70 ? 'green' : 'neutral' }" />
      <StatCard icon="wallet" label="Valor faturável" :value="money(report.totals.amount)" />
      <StatCard icon="layers" :label="`Linhas (${column.toLowerCase()})`" :value="String(report.rows.length)" />
    </div>

    <section v-if="report" class="card overflow-hidden" :class="{ 'opacity-60': processing }">
      <div class="card-header">
        <h2 class="card-title">Por {{ column.toLowerCase() }}</h2>
        <span class="text-xs text-fg-muted">{{ new Date(`${report.from}T12:00:00`).toLocaleDateString('pt-BR') }} a {{ new Date(`${report.to}T12:00:00`).toLocaleDateString('pt-BR') }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="table-clean" data-test="report">
          <thead>
            <tr>
              <th>{{ column }}</th>
              <th class="hidden w-1/3 md:table-cell"><span class="sr-only">Proporção das horas</span></th>
              <th class="text-right">Horas</th>
              <th class="text-right">Faturáveis</th>
              <th class="text-right">Valor</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in report.rows" :key="row.key || row.label">
              <td class="font-medium text-fg">{{ row.label }}</td>
              <td class="hidden md:table-cell">
                <div class="flex items-center gap-3">
                  <div class="h-1.5 flex-1 rounded-full bg-elevated">
                    <div class="h-1.5 rounded-full bg-brand-500" :style="{ width: `${maxMinutes ? (row.minutes / maxMinutes) * 100 : 0}%` }" />
                  </div>
                  <span class="w-10 text-right text-xs text-fg-muted tabular-nums">{{ report.totals.minutes ? Math.round((row.minutes / report.totals.minutes) * 100) : 0 }}%</span>
                </div>
              </td>
              <td class="text-right tabular-nums">{{ formatMinutes(row.minutes) }}</td>
              <td class="text-right text-fg-muted tabular-nums">{{ formatMinutes(row.billable_minutes) }}</td>
              <td class="text-right font-medium whitespace-nowrap tabular-nums">{{ money(row.amount) }}</td>
            </tr>
            <tr v-if="report.rows.length === 0">
              <td colspan="5"><EmptyState icon="chart" title="Nenhuma hora lançada no período." /></td>
            </tr>
          </tbody>
          <tfoot v-if="report.rows.length" class="border-t border-line bg-surface-2/60 font-semibold">
            <tr>
              <td class="px-5 py-3">Total</td>
              <td class="hidden md:table-cell" />
              <td class="px-5 py-3 text-right tabular-nums" data-test="total-hours">{{ formatMinutes(report.totals.minutes) }}</td>
              <td class="px-5 py-3 text-right tabular-nums">{{ formatMinutes(report.totals.billable_minutes) }}</td>
              <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums">{{ money(report.totals.amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </AppLayout>
</template>
