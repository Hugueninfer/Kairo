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
  quote_statuses: Option<QuoteStatus>[]
  quote_item_types: Option<QuoteItemType>[]
  contract_statuses: Option<ContractStatus>[]
  invoice_statuses: Option<InvoiceStatus>[]
  payment_methods: Option<PaymentMethod>[]
  recurrence_frequencies: Option<RecurrenceFrequency>[]
  reminder_stages: Option<ReminderStage>[]
}

export type TimeEntryStatus = 'pending' | 'approved' | 'rejected'

export interface TimeEntry {
  id: string
  description: string | null
  started_at: string
  ended_at: string | null
  is_running: boolean
  duration_minutes: number
  billable: boolean
  hourly_rate: string | null
  amount: string | null
  status: Option<TimeEntryStatus>
  is_invoiced: boolean
  user?: { id: number; name: string }
  project?: { id: string; name: string }
  task?: { id: string; title: string } | null
}

/** Planilha da semana (segunda a domingo), em minutos. */
export interface WeekSheet {
  week_start: string
  days: string[]
  rows: {
    project: { id: string; name: string }
    task: { id: string; title: string } | null
    minutes: number[]
    total: number
  }[]
  totals: { minutes: number[]; total: number; billable_minutes: number; amount: string }
}

export type QuoteStatus = 'draft' | 'sent' | 'viewed' | 'accepted' | 'rejected' | 'expired'
export type QuoteItemType = 'fixed' | 'hourly'
export type ContractStatus = 'active' | 'completed' | 'cancelled'

export interface QuoteItem {
  description: string
  type: Option<QuoteItemType> & { unit: string }
  quantity: string
  unit_price: string
  total: string
}

interface QuoteTotals {
  subtotal: string
  discount: string
  tax_rate: string
  tax_amount: string
  total: string
}

export interface Quote extends QuoteTotals {
  id: string
  number: string
  title: string
  status: Option<QuoteStatus> & { is_decided: boolean }
  valid_until: string
  introduction: string | null
  terms: string | null
  sent_at: string | null
  viewed_at: string | null
  decided_at: string | null
  signer_name: string | null
  decision_note: string | null
  client?: { id: string; name: string; email: string | null }
  items?: QuoteItem[]
  project?: { id: string; name: string } | null
  contract?: { id: string; number: string } | null
  created_at: string | null
}

/** O que o cliente vê pelo link público. */
export interface PublicQuote extends QuoteTotals {
  number: string
  title: string
  status: Option<QuoteStatus>
  awaits_decision: boolean
  valid_until: string
  introduction: string | null
  terms: string | null
  currency: string
  decided_at: string | null
  signer_name: string | null
  company: { name: string }
  client: { name: string }
  items: QuoteItem[]
}

export interface Contract {
  id: string
  number: string
  title: string
  status: Option<ContractStatus>
  total: string
  terms: string | null
  signed_at: string
  signer_name: string
  client?: { id: string; name: string }
  project?: { id: string; name: string } | null
  quote?: { id: string; number: string }
  recurrence: ContractRecurrence | null
}

export type RecurrenceFrequency = 'monthly' | 'quarterly' | 'yearly'

export interface ContractRecurrence {
  frequency: Option<RecurrenceFrequency>
  amount: string
  next_billing_on: string | null
  ends_on: string | null
  payment_term_days: number
  auto_send: boolean
}

export type ReminderStage = 'before_due' | 'due_today' | 'overdue_3' | 'overdue_7'

export interface BillingSettings {
  reminders_enabled: boolean
  reminder_stages: ReminderStage[]
  reminder_message: string | null
}

export type InvoiceStatus = 'draft' | 'open' | 'partially_paid' | 'overdue' | 'paid' | 'cancelled'
export type PaymentMethod = 'pix' | 'boleto' | 'credit_card' | 'transfer' | 'cash' | 'other'

export interface InvoiceItem {
  description: string
  quantity: string
  unit_price: string
  total: string
}

export interface Payment {
  id: string
  amount: string
  method: Option<PaymentMethod>
  paid_at: string
  via_gateway: boolean
}

export interface Invoice {
  id: string
  number: string
  status: Option<InvoiceStatus> & { awaits_payment: boolean }
  issue_date: string
  due_date: string
  billing_period_start: string | null
  notes: string | null
  subtotal: string
  discount: string
  total: string
  amount_paid: string
  payment_url: string | null
  sent_at: string | null
  paid_at: string | null
  cancelled_at: string | null
  client?: { id: string; name: string; email: string | null }
  project?: { id: string; name: string } | null
  contract?: { id: string; number: string } | null
  items?: InvoiceItem[]
  payments?: Payment[]
  reminders?: { stage: Option<ReminderStage>; sent_at: string }[]
  time_entries_count?: number
}

export interface InvoiceSummary {
  receivable: string
  overdue: string
  received_this_month: string
}
