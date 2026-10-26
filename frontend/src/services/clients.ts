import { http } from './http'
import type { ApiResource, Client, Paginated } from '@/types/api'

export interface ClientPayload {
  name: string
  email?: string | null
  phone?: string | null
  document?: string | null
  notes?: string | null
}

export const clientsApi = {
  async list(params: { search?: string; page?: number; per_page?: number } = {}): Promise<Paginated<Client>> {
    const { data } = await http.get<Paginated<Client>>('/api/v1/company/clients', { params })
    return data
  },

  async create(payload: ClientPayload): Promise<Client> {
    const { data } = await http.post<ApiResource<Client>>('/api/v1/company/clients', payload)
    return data.data
  },

  async update(id: string, payload: ClientPayload): Promise<Client> {
    const { data } = await http.put<ApiResource<Client>>(`/api/v1/company/clients/${encodeURIComponent(id)}`, payload)
    return data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/api/v1/company/clients/${encodeURIComponent(id)}`)
  },
}
