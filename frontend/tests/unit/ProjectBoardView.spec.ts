import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { companiesApi } from '@/services/companies'
import { projectsApi, tasksApi } from '@/services/projects'
import { useCompanyStore } from '@/stores/company'
import type { Options, Project, Task } from '@/types/api'
import ProjectBoardView from '@/views/projects/ProjectBoardView.vue'

const options: Options = {
  project_statuses: [{ value: 'active', label: 'Em andamento' }],
  billing_types: [{ value: 'hourly', label: 'Por hora' }],
  task_statuses: [
    { value: 'todo', label: 'A fazer' },
    { value: 'in_progress', label: 'Em andamento' },
    { value: 'review', label: 'Em revisão' },
    { value: 'done', label: 'Concluída' },
  ],
  task_priorities: [{ value: 'medium', label: 'Média' }],
  quote_statuses: [],
  invoice_statuses: [],
  payment_methods: [],
  recurrence_frequencies: [],
  reminder_stages: [],
  audit_subjects: [],
  quote_item_types: [],
  contract_statuses: [],
}

const project: Project = {
  id: 'p1',
  name: 'Site',
  description: null,
  status: { value: 'active', label: 'Em andamento' },
  billing_type: { value: 'hourly', label: 'Por hora' },
  hourly_rate: null,
  budget: null,
  starts_on: null,
  due_on: null,
  client: null,
}

function task(id: string, status: Task['status']['value'], position: number): Task {
  return {
    id,
    title: `Tarefa ${id}`,
    description: null,
    status: { value: status, label: status },
    priority: { value: 'medium', label: 'Média' },
    position,
    estimate_minutes: null,
    due_on: null,
    completed_at: null,
    assignee: null,
  }
}

async function mountBoard(permissions: string[]) {
  setActivePinia(createPinia())
  useCompanyStore().access = { role: null, permissions }
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', name: 'projects', component: { template: '<div />' } }] })
  await router.push('/')
  const wrapper = mount(ProjectBoardView, { props: { id: 'p1' }, global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('ProjectBoardView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.spyOn(projectsApi, 'options').mockResolvedValue(options)
    vi.spyOn(projectsApi, 'get').mockResolvedValue(project)
    vi.spyOn(companiesApi, 'members').mockResolvedValue([])
    vi.spyOn(tasksApi, 'board').mockResolvedValue([task('b', 'todo', 1), task('a', 'todo', 0), task('c', 'done', 0)])
  })

  it('distribui as tarefas nas colunas, na ordem', async () => {
    const wrapper = await mountBoard(['projects.view', 'tasks.manage'])

    const todo = wrapper.get('[data-test="column-todo"]').findAll('[data-card]').map((card) => card.text())
    expect(todo[0]).toContain('Tarefa a')
    expect(todo[1]).toContain('Tarefa b')
    expect(wrapper.get('[data-test="column-done"]').findAll('[data-card]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Adicionar tarefa')
  })

  it('sem permissão de tarefas, não deixa arrastar nem criar', async () => {
    const wrapper = await mountBoard(['projects.view'])

    expect(wrapper.get('[data-card="a"]').attributes('draggable')).toBe('false')
    expect(wrapper.text()).not.toContain('+ Adicionar tarefa')
  })
})
