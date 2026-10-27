import { ensureCsrfCookie, http } from './http'
import type { ApiResource, Contract, ContractStatus, Paginated, PublicQuote, Quote, QuoteItemType } from '@/types/api'

export interface QuoteItemPayload {
  description: string
  type: QuoteItemType
  quantity: string
  unit_price: string
}

export interface QuotePayload {
  client_id: string
  title: string
  valid_until: string
  introduction?: string | null
  terms?: string | null
  discount?: string
  tax_rate?: string
  items: QuoteItemPayload[]
}

const quote = (id: string) => `/api/v1/company/quotes/${encodeURIComponent(id)}`

export const quotesApi = {
  async list(params: { search?: string; status?: string; client?: string; page?: number } = {}): Promise<Paginated<Quote>> {
    const { data } = await http.get<Paginated<Quote>>('/api/v1/company/quotes', { params })
    return data
  },

  async get(id: string): Promise<Quote> {
    const { data } = await http.get<ApiResource<Quote>>(quote(id))
    return data.data
  },

  async create(payload: QuotePayload): Promise<Quote> {
    const { data } = await http.post<ApiResource<Quote>>('/api/v1/company/quotes', payload)
    return data.data
  },

  async update(id: string, payload: QuotePayload): Promise<Quote> {
    const { data } = await http.put<ApiResource<Quote>>(quote(id), payload)
    return data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(quote(id))
  },

  async send(id: string): Promise<Quote> {
    const { data } = await http.post<ApiResource<Quote>>(`${quote(id)}/send`)
    return data.data
  },

  async duplicate(id: string): Promise<Quote> {
    const { data } = await http.post<ApiResource<Quote>>(`${quote(id)}/duplicate`)
    return data.data
  },

  /** Link de download do PDF (usa o cookie da sessão). */
  pdfUrl(id: string): string {
    return `${import.meta.env.VITE_API_URL || ''}${quote(id)}/pdf`
  },
}

export const contractsApi = {
  async list(params: { search?: string; status?: string; page?: number } = {}): Promise<Paginated<Contract>> {
    const { data } = await http.get<Paginated<Contract>>('/api/v1/company/contracts', { params })
    return data
  },

  async close(id: string, status: Exclude<ContractStatus, 'active'>): Promise<Contract> {
    const { data } = await http.put<ApiResource<Contract>>(`/api/v1/company/contracts/${encodeURIComponent(id)}/status`, { status })
    return data.data
  },
}

/**
 * Página pública do orçamento. expires e signature vêm do link do e-mail e são
 * repassados como estão: a API confere a assinatura.
 */
export const publicQuoteApi = {
  async get(id: string, signed: { expires: string; signature: string }): Promise<PublicQuote> {
    const { data } = await http.get<ApiResource<PublicQuote>>(`/api/v1/public/quotes/${encodeURIComponent(id)}`, { params: signed })
    return data.data
  },

  async decide(
    id: string,
    signed: { expires: string; signature: string },
    payload: { decision: 'accept' | 'reject'; signer_name?: string; note?: string },
  ): Promise<PublicQuote> {
    await ensureCsrfCookie()
    const { data } = await http.post<ApiResource<PublicQuote>>(`/api/v1/public/quotes/${encodeURIComponent(id)}`, payload, { params: signed })
    return data.data
  },
}
