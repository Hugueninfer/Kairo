<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import FormAlert from '@/components/ui/FormAlert.vue'
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

/** CSV com ponto e vírgula, que o Excel em português abre direto nas colunas certas. */
function downloadCsv() {
  if (!report.value) return
  const decimal = (value: number) => value.toFixed(2).replace('.', ',')
  const lines = [
    [column.value, 'Horas', 'Horas faturáveis', 'Valor'],
    ...report.value.rows.map((row) => [row.label, decimal(row.minutes / 60), decimal(row.billable_minutes / 60), decimal(Number(row.amount))]),
    ['Total', decimal(report.value.totals.minutes / 60), decimal(report.value.totals.billable_minutes / 60), decimal(Number(report.value.totals.amount))],
  ]
  const csv = lines.map((line) => line.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(';')).join('\r\n')
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
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold">Relatório de horas</h1>
        <p class="text-sm text-slate-600">Horas lançadas no período (as recusadas ficam de fora), com o valor das faturáveis.</p>
      </div>
      <button
        type="button"
        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50 disabled:opacity-50"
        :disabled="!report || processing"
        data-test="download-csv"
        @click="downloadCsv"
      >
        Baixar CSV
      </button>
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <div class="flex overflow-hidden rounded-lg border border-slate-300" role="tablist" aria-label="Agrupar por">
        <button
          v-for="group in groups"
          :key="group.value"
          type="button"
          role="tab"
          class="border-r border-slate-200 px-3 py-1.5 text-sm last:border-r-0"
          :class="filters.group === group.value ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'"
          :aria-selected="filters.group === group.value"
          :data-group="group.value"
          @click="chooseGroup(group.value)"
        >
          {{ group.label }}
        </button>
      </div>
      <label class="text-sm">
        <span class="mb-1 block text-slate-600">De</span>
        <input v-model="filters.from" type="date" class="rounded-lg border border-slate-300 px-2 py-1.5" data-test="from" @change="load()" />
      </label>
      <label class="text-sm">
        <span class="mb-1 block text-slate-600">Até</span>
        <input v-model="filters.to" type="date" class="rounded-lg border border-slate-300 px-2 py-1.5" data-test="to" @change="load()" />
      </label>
      <div class="flex gap-2 text-sm">
        <button type="button" class="text-brand-600 hover:underline" @click="setMonth(0)">Este mês</button>
        <button type="button" class="text-brand-600 hover:underline" @click="setMonth(-1)">Mês passado</button>
      </div>
      <span v-if="processing" class="ml-auto text-sm text-slate-500" data-test="processing">Calculando…</span>
    </div>

    <FormAlert :message="error" />

    <section v-if="report" class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200" :class="{ 'opacity-60': processing }">
      <table class="w-full text-sm" data-test="report">
        <thead class="border-b border-slate-200 text-left text-slate-500">
          <tr>
            <th class="px-4 py-3 font-medium">{{ column }}</th>
            <th class="w-1/3 px-4 py-3 font-medium"><span class="sr-only">Proporção das horas</span></th>
            <th class="px-4 py-3 text-right font-medium">Horas</th>
            <th class="px-4 py-3 text-right font-medium">Faturáveis</th>
            <th class="px-4 py-3 text-right font-medium">Valor</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in report.rows" :key="row.key || row.label">
            <td class="px-4 py-2.5 font-medium">{{ row.label }}</td>
            <td class="px-4 py-2.5">
              <div class="h-2 rounded-full bg-brand-500" :style="{ width: `${maxMinutes ? (row.minutes / maxMinutes) * 100 : 0}%` }" />
            </td>
            <td class="px-4 py-2.5 text-right tabular-nums">{{ formatMinutes(row.minutes) }}</td>
            <td class="px-4 py-2.5 text-right tabular-nums">{{ formatMinutes(row.billable_minutes) }}</td>
            <td class="px-4 py-2.5 text-right tabular-nums">{{ money(row.amount) }}</td>
          </tr>
          <tr v-if="report.rows.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-slate-500">Nenhuma hora lançada no período.</td>
          </tr>
        </tbody>
        <tfoot v-if="report.rows.length" class="border-t border-slate-200 font-semibold">
          <tr>
            <td class="px-4 py-3">Total</td>
            <td />
            <td class="px-4 py-3 text-right tabular-nums" data-test="total-hours">{{ formatMinutes(report.totals.minutes) }}</td>
            <td class="px-4 py-3 text-right tabular-nums">{{ formatMinutes(report.totals.billable_minutes) }}</td>
            <td class="px-4 py-3 text-right tabular-nums">{{ money(report.totals.amount) }}</td>
          </tr>
        </tfoot>
      </table>
    </section>
  </AppLayout>
</template>
