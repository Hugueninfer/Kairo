import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { timesheetApi } from '@/services/timesheet'
import { useTimerStore } from '@/stores/timer'
import type { TimeEntry } from '@/types/api'

function entry(startedAt: string): TimeEntry {
  return { id: 'e1', started_at: startedAt, is_running: true } as TimeEntry
}

describe('timer store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-26T12:00:00Z'))
  })

  afterEach(() => vi.useRealTimers())

  it('conta o tempo a partir do started_at da API', async () => {
    vi.spyOn(timesheetApi, 'timer').mockResolvedValue(entry('2026-10-26T11:00:00Z'))
    const timer = useTimerStore()
    await timer.load()
    expect(timer.elapsedSeconds).toBe(3600)

    vi.advanceTimersByTime(5000)
    expect(timer.elapsedSeconds).toBe(3605)
  })

  it('só busca uma vez sem force', async () => {
    const spy = vi.spyOn(timesheetApi, 'timer').mockResolvedValue(null)
    const timer = useTimerStore()
    await timer.load()
    await timer.load()
    expect(spy).toHaveBeenCalledTimes(1)
    await timer.load(true)
    expect(spy).toHaveBeenCalledTimes(2)
  })

  it('para o timer e zera', async () => {
    vi.spyOn(timesheetApi, 'start').mockResolvedValue(entry('2026-10-26T12:00:00Z'))
    vi.spyOn(timesheetApi, 'stop').mockResolvedValue({ ...entry('2026-10-26T12:00:00Z'), is_running: false })
    const timer = useTimerStore()
    await timer.start({ project_id: 'p1' })
    expect(timer.running?.id).toBe('e1')

    await timer.stop()
    expect(timer.running).toBeNull()
    expect(timer.elapsedSeconds).toBe(0)
  })
})
