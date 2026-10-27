import { http } from './http'
import type { ApiResource, TimeEntry, TimeEntryStatus, WeekSheet } from '@/types/api'

export interface TimeEntryPayload {
  project_id: string
  task_id?: string | null
  date: string
  start?: string | null
  minutes: number
  description?: string | null
  billable?: boolean | null
}

export interface TimerPayload {
  project_id: string
  task_id?: string | null
  description?: string | null
}

/** Quem consultar: um usuário, toda a equipe (all) ou, sem nada, quem está logado. */
export interface Who {
  user?: number
  all?: boolean
}

const base = '/api/v1/company/time-entries'

function whoParams(who: Who) {
  return { user: who.user, all: who.all ? 1 : undefined }
}

export const timesheetApi = {
  async week(week: string, who: Who = {}): Promise<WeekSheet> {
    const { data } = await http.get<ApiResource<WeekSheet>>(`${base}/week`, { params: { week, ...whoParams(who) } })
    return data.data
  },

  async list(from: string, to: string, who: Who = {}): Promise<TimeEntry[]> {
    const { data } = await http.get<ApiResource<TimeEntry[]>>(base, { params: { from, to, ...whoParams(who) } })
    return data.data
  },

  exportUrl(from: string, to: string, who: Who = {}): string {
    const params = new URLSearchParams({ from, to })
    if (who.user) params.set('user', String(who.user))
    if (who.all) params.set('all', '1')
    return `${import.meta.env.VITE_API_URL || ''}${base}/export?${params.toString()}`
  },

  async create(payload: TimeEntryPayload): Promise<TimeEntry> {
    const { data } = await http.post<ApiResource<TimeEntry>>(base, payload)
    return data.data
  },

  async update(id: string, payload: TimeEntryPayload): Promise<TimeEntry> {
    const { data } = await http.put<ApiResource<TimeEntry>>(`${base}/${encodeURIComponent(id)}`, payload)
    return data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`${base}/${encodeURIComponent(id)}`)
  },

  async review(ids: string[], status: Exclude<TimeEntryStatus, 'pending'>): Promise<number> {
    const { data } = await http.post<ApiResource<{ updated: number }>>(`${base}/review`, { ids, status })
    return data.data.updated
  },

  async timer(): Promise<TimeEntry | null> {
    const { data } = await http.get<ApiResource<TimeEntry | null>>(`${base}/timer`)
    return data.data
  },

  async start(payload: TimerPayload): Promise<TimeEntry> {
    const { data } = await http.post<ApiResource<TimeEntry>>(`${base}/timer`, payload)
    return data.data
  },

  async stop(): Promise<TimeEntry> {
    const { data } = await http.delete<ApiResource<TimeEntry>>(`${base}/timer`)
    return data.data
  },
}
