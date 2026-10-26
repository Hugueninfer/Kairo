export interface User {
  id: number
  name: string
  email: string
  email_verified: boolean
  created_at: string | null
}

export interface ApiResource<T> {
  data: T
}

/** Formato padronizado de erro da API (BusinessException, 401, 403, 404, 422). */
export interface ApiErrorBody {
  message: string
  code?: string
  errors?: Record<string, string[]>
}

export interface Company {
  id: string
  name: string
  document: string | null
  currency: string
  timezone: string
  default_hourly_rate: string | null
  is_owner: boolean
  is_current: boolean
}

export interface RoleRef {
  id: number
  name: string
}

export interface Member {
  id: number
  name: string
  email: string
  is_owner: boolean
  role: RoleRef | null
}

export interface Invitation {
  id: string
  email: string
  status: { value: string; label: string }
  expires_at: string
  invited_by?: string
  role?: RoleRef | null
}

/** Papel da empresa. O de dono (is_locked) sempre tem tudo e não pode ser editado. */
export interface Role {
  id: number
  name: string
  system_key: 'owner' | 'admin' | 'finance' | 'member' | null
  is_system: boolean
  is_locked: boolean
  permissions: string[]
  members_count: number
}

export interface PermissionGroup {
  group: string
  permissions: { key: string; label: string }[]
}

/** Papel e permissões de quem está logado na empresa ativa. */
export interface CompanyAccess {
  role: RoleRef | null
  permissions: string[]
}

export interface InvitationPreview {
  company_name: string
  invited_by: string
  email: string
  expires_at: string
}

export interface Option<T extends string = string> {
  value: T
  label: string
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface Client {
  id: string
  name: string
  email: string | null
  phone: string | null
  document: string | null
  notes: string | null
  projects_count?: number
  created_at: string | null
}

export type ProjectStatus = 'active' | 'paused' | 'completed' | 'archived'
export type BillingType = 'hourly' | 'fixed' | 'non_billable'

export interface Project {
  id: string
  name: string
  description: string | null
  status: Option<ProjectStatus>
  billing_type: Option<BillingType>
  hourly_rate: string | null
  budget: string | null
  starts_on: string | null
  due_on: string | null
  client?: { id: string; name: string } | null
  open_tasks_count?: number
  done_tasks_count?: number
}

export type TaskStatus = 'todo' | 'in_progress' | 'review' | 'done'
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent'

export interface Task {
  id: string
  title: string
  description: string | null
  status: Option<TaskStatus>
  priority: Option<TaskPriority>
  position: number
  estimate_minutes: number | null
  due_on: string | null
  completed_at: string | null
  assignee?: { id: number; name: string } | null
  project?: { id: string; name: string }
}

/** Opções dos selects, vindas dos Enums da API. */
export interface Options {
  project_statuses: Option<ProjectStatus>[]
  billing_types: Option<BillingType>[]
  task_statuses: Option<TaskStatus>[]
  task_priorities: Option<TaskPriority>[]
}
