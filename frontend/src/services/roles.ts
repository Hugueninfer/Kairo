import { http } from './http'
import type { ApiResource, PermissionGroup, Role } from '@/types/api'

export interface RolePayload {
  name: string
  permissions: string[]
}

export const rolesApi = {
  async list(): Promise<Role[]> {
    const { data } = await http.get<ApiResource<Role[]>>('/api/v1/company/roles')
    return data.data
  },

  async catalog(): Promise<PermissionGroup[]> {
    const { data } = await http.get<ApiResource<PermissionGroup[]>>('/api/v1/company/permissions')
    return data.data
  },

  async create(payload: RolePayload): Promise<Role> {
    const { data } = await http.post<ApiResource<Role>>('/api/v1/company/roles', payload)
    return data.data
  },

  async update(id: number, payload: RolePayload): Promise<Role> {
    const { data } = await http.put<ApiResource<Role>>(`/api/v1/company/roles/${id}`, payload)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/api/v1/company/roles/${id}`)
  },
}
