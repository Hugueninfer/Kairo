import { http } from './http'
import type { ApiResource, BillingType, Options, Paginated, Project, ProjectStatus, Task, TaskPriority, TaskStatus } from '@/types/api'

export interface ProjectPayload {
  name: string
  client_id?: string | null
  description?: string | null
  status?: ProjectStatus
  billing_type?: BillingType
  hourly_rate?: string | null
  budget?: string | null
  starts_on?: string | null
  due_on?: string | null
}

export interface TaskPayload {
  title: string
  description?: string | null
  status?: TaskStatus
  priority?: TaskPriority
  assignee_id?: number | null
  estimate_minutes?: number | null
  due_on?: string | null
}

const project = (id: string) => `/api/v1/company/projects/${encodeURIComponent(id)}`

export const projectsApi = {
  async options(): Promise<Options> {
    const { data } = await http.get<ApiResource<Options>>('/api/v1/options')
    return data.data
  },

  async list(params: { search?: string; status?: string; client?: string; page?: number; per_page?: number } = {}): Promise<Paginated<Project>> {
    const { data } = await http.get<Paginated<Project>>('/api/v1/company/projects', { params })
    return data
  },

  async get(id: string): Promise<Project> {
    const { data } = await http.get<ApiResource<Project>>(project(id))
    return data.data
  },

  async create(payload: ProjectPayload): Promise<Project> {
    const { data } = await http.post<ApiResource<Project>>('/api/v1/company/projects', payload)
    return data.data
  },

  async update(id: string, payload: ProjectPayload): Promise<Project> {
    const { data } = await http.put<ApiResource<Project>>(project(id), payload)
    return data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(project(id))
  },
}

export const tasksApi = {
  async board(projectId: string): Promise<Task[]> {
    const { data } = await http.get<ApiResource<Task[]>>(`${project(projectId)}/tasks`)
    return data.data
  },

  async mine(): Promise<Task[]> {
    const { data } = await http.get<ApiResource<Task[]>>('/api/v1/company/tasks/mine')
    return data.data
  },

  async create(projectId: string, payload: TaskPayload): Promise<Task> {
    const { data } = await http.post<ApiResource<Task>>(`${project(projectId)}/tasks`, payload)
    return data.data
  },

  async update(projectId: string, taskId: string, payload: TaskPayload): Promise<Task> {
    const { data } = await http.put<ApiResource<Task>>(`${project(projectId)}/tasks/${encodeURIComponent(taskId)}`, payload)
    return data.data
  },

  async move(projectId: string, taskId: string, status: TaskStatus, position: number): Promise<Task> {
    const { data } = await http.put<ApiResource<Task>>(`${project(projectId)}/tasks/${encodeURIComponent(taskId)}/move`, {
      status,
      position,
    })
    return data.data
  },

  async remove(projectId: string, taskId: string): Promise<void> {
    await http.delete(`${project(projectId)}/tasks/${encodeURIComponent(taskId)}`)
  },
}
