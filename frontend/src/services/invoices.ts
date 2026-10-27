import { http } from './http'
import type { ApiResource, Invoice, InvoiceSummary, Paginated, PaymentMethod } from '@/types/api'

export interface InvoiceItemPayload {
  description: string
  quantity: string
  unit_price: string
}

export interface InvoicePayload {
  client_id: string
  project_id?: string | null
  due_date: string
  notes?: string | null
  discount?: string
  items: InvoiceItemPayload[]
}

const base = '/api/v1/company/invoices'
const invoice = (id: string) => `${base}/${encodeURIComponent(id)}`

export const invoicesApi = {
  async list(params: { search?: string; status?: string; page?: number } = {}): Promise<Paginated<Invoice> & { summary: InvoiceSummary }> {
    const { data } = await http.get<Paginated<Invoice> & { summary: InvoiceSummary }>(base, { params })
    return data
  },

  async get(id: string): Promise<Invoice> {
    const { data } = await http.get<ApiResource<Invoice>>(invoice(id))
    return data.data
  },

  async create(payload: InvoicePayload): Promise<Invoice> {
    const { data } = await http.post<ApiResource<Invoice>>(base, payload)
    return data.data
  },

  async fromHours(payload: { project_id: string; from: string; to: string; due_date: string }): Promise<Invoice> {
    const { data } = await http.post<ApiResource<Invoice>>(`${base}/from-hours`, payload)
    return data.data
  },

  async fromContract(payload: { contract_id: string; percent: string; due_date: string }): Promise<Invoice> {
    const { data } = await http.post<ApiResource<Invoice>>(`${base}/from-contract`, payload)
    return data.data
  },

  async update(id: string, payload: InvoicePayload): Promise<Invoice> {
    const { data } = await http.put<ApiResource<Invoice>>(invoice(id), payload)
    return data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(invoice(id))
  },

  async send(id: string): Promise<Invoice> {
    const { data } = await http.post<ApiResource<Invoice>>(`${invoice(id)}/send`)
    return data.data
  },

  async cancel(id: string): Promise<Invoice> {
    const { data } = await http.post<ApiResource<Invoice>>(`${invoice(id)}/cancel`)
    return data.data
  },

  async recordPayment(id: string, payload: { amount: string; method: PaymentMethod; paid_on: string }): Promise<Invoice> {
    const { data } = await http.post<ApiResource<Invoice>>(`${invoice(id)}/payments`, payload)
    return data.data
  },

  pdfUrl(id: string): string {
    return `${import.meta.env.VITE_API_URL || ''}${invoice(id)}/pdf`
  },
}
