import { http } from './http'
import type { ApiResource, Company, CompanyAccess, Invitation, InvitationPreview, Member } from '@/types/api'

export interface CompanyPayload {
  name: string
  document?: string | null
  currency?: string
  timezone?: string
  default_hourly_rate?: string | number | null
}

export const companiesApi = {
  async list(): Promise<Company[]> {
    const { data } = await http.get<ApiResource<Company[]>>('/api/v1/companies')
    return data.data
  },

  async create(payload: CompanyPayload): Promise<Company> {
    const { data } = await http.post<ApiResource<Company>>('/api/v1/companies', payload)
    return data.data
  },

  async switchTo(id: string): Promise<Company> {
    const { data } = await http.post<ApiResource<Company>>(`/api/v1/companies/${encodeURIComponent(id)}/switch`)
    return data.data
  },

  /** Empresa ativa com o papel e as permissões de quem está logado. */
  async access(): Promise<CompanyAccess> {
    const { data } = await http.get<ApiResource<Company> & { meta: CompanyAccess }>('/api/v1/company')
    return data.meta
  },

  async update(payload: CompanyPayload): Promise<Company> {
    const { data } = await http.patch<ApiResource<Company>>('/api/v1/company', payload)
    return data.data
  },

  async members(): Promise<Member[]> {
    const { data } = await http.get<ApiResource<Member[]>>('/api/v1/company/members')
    return data.data
  },

  async changeMemberRole(memberId: number, roleId: number): Promise<void> {
    await http.put(`/api/v1/company/members/${memberId}/role`, { role_id: roleId })
  },

  async removeMember(id: number): Promise<void> {
    await http.delete(`/api/v1/company/members/${id}`)
  },

  async invitations(): Promise<Invitation[]> {
    const { data } = await http.get<ApiResource<Invitation[]>>('/api/v1/company/invitations')
    return data.data
  },

  async invite(email: string, roleId: number | null = null): Promise<Invitation> {
    const { data } = await http.post<ApiResource<Invitation>>('/api/v1/company/invitations', { email, role_id: roleId })
    return data.data
  },

  async revokeInvitation(id: string): Promise<void> {
    await http.delete(`/api/v1/company/invitations/${encodeURIComponent(id)}`)
  },

  async previewInvitation(token: string): Promise<InvitationPreview> {
    const { data } = await http.get<ApiResource<InvitationPreview>>(`/api/v1/invitations/${encodeURIComponent(token)}`)
    return data.data
  },

  async acceptInvitation(token: string): Promise<Company> {
    const { data } = await http.post<ApiResource<Company>>(`/api/v1/invitations/${encodeURIComponent(token)}/accept`)
    return data.data
  },
}
