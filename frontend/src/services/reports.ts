import { http } from './http'
import type { ApiResource, AuditEntry, AuditSubject, Dashboard, HoursReport, Paginated, ReportGroup } from '@/types/api'

export interface HoursReportResult {
  status: 'ready' | 'processing'
  data: HoursReport | null
}

export interface AuditParams {
  subject?: AuditSubject
  user?: number
  from?: string
  to?: string
  page?: number
}

export const reportsApi = {
  async dashboard(): Promise<Dashboard> {
    const { data } = await http.get<ApiResource<Dashboard>>('/api/v1/company/dashboard')
    return data.data
  },

  /** A API calcula na fila: enquanto não fica pronto, responde 202 com status "processing". */
  async hours(params: { from: string; to: string; group: ReportGroup }): Promise<HoursReportResult> {
    const { data } = await http.get<HoursReportResult>('/api/v1/company/reports/hours', { params })
    return data
  },

  async audit(params: AuditParams = {}): Promise<Paginated<AuditEntry>> {
    const { data } = await http.get<Paginated<AuditEntry>>('/api/v1/company/audit', { params })
    return data
  },
}
