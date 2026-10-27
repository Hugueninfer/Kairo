import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { timesheetApi, type TimerPayload } from '@/services/timesheet'
import type { TimeEntry } from '@/types/api'

/**
 * Timer de horas da pessoa logada. O relógio é calculado no front a partir do
 * started_at da API, então recarregar a página não perde a contagem.
 */
export const useTimerStore = defineStore('timer', () => {
  const running = ref<TimeEntry | null>(null)
  const loaded = ref(false)
  const now = ref(Date.now())
  let ticker: ReturnType<typeof setInterval> | undefined

  const elapsedSeconds = computed(() => (running.value ? (now.value - Date.parse(running.value.started_at)) / 1000 : 0))

  function tick(active: boolean) {
    clearInterval(ticker)
    ticker = undefined
    if (active) {
      now.value = Date.now()
      ticker = setInterval(() => (now.value = Date.now()), 1000)
    }
  }

  /** Busca o timer na API. Sem force, só na primeira vez (o layout chama a cada tela). */
  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    loaded.value = true
    running.value = await timesheetApi.timer().catch(() => null)
    tick(running.value !== null)
  }

  async function start(payload: TimerPayload): Promise<TimeEntry> {
    running.value = await timesheetApi.start(payload)
    tick(true)
    return running.value
  }

  async function stop(): Promise<TimeEntry> {
    const stopped = await timesheetApi.stop()
    running.value = null
    tick(false)
    return stopped
  }

  function reset() {
    running.value = null
    loaded.value = false
    tick(false)
  }

  return { running, elapsedSeconds, load, start, stop, reset }
})
