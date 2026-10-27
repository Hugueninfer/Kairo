import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { boardsApi } from '@/services/boards'
import { projectsApi } from '@/services/projects'
import { useCompanyStore } from '@/stores/company'
import type { Board, Project } from '@/types/api'
import BoardsView from '@/views/boards/BoardsView.vue'

const meta = { current_page: 1, last_page: 1, per_page: 24, total: 1 }
const board: Board = {
  id: 'b1',
  title: 'Mapa do site',
  updated_at: '2026-10-27T15:00:00Z',
  project: { id: 'p1', name: 'Site da padaria' },
  elements_count: 3,
}

async function mountView(permissions: string[]) {
  setActivePinia(createPinia())
  useCompanyStore().access = { role: null, permissions }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/boards', name: 'boards', component: BoardsView },
      { path: '/boards/:id', name: 'board', component: { template: '<div />' } },
      { path: '/:any(.*)', component: { template: '<div />' } },
    ],
  })
  await router.push('/boards')
  const wrapper = mount(BoardsView, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

describe('BoardsView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.spyOn(boardsApi, 'list').mockResolvedValue({ data: [board], meta })
  })

  it('lista os boards com projeto e quantidade de elementos', async () => {
    const { wrapper } = await mountView(['boards.view'])

    const card = wrapper.get('[data-test="boards"] li').text()
    expect(card).toContain('Mapa do site')
    expect(card).toContain('Site da padaria · 3 elementos')
    expect(wrapper.text()).not.toContain('Novo board')
  })

  it('cria o board ligado ao projeto e abre o canvas', async () => {
    vi.spyOn(projectsApi, 'list').mockResolvedValue({ data: [{ id: 'p1', name: 'Site da padaria' } as Project], meta })
    const create = vi.spyOn(boardsApi, 'create').mockResolvedValue({ ...board, id: 'novo' })
    const { wrapper, router } = await mountView(['boards.view', 'boards.manage', 'projects.view'])

    await wrapper.get('button.bg-brand-600').trigger('click')
    await flushPromises()
    await wrapper.get('#board-title').setValue('Fluxo de pedidos')
    await wrapper.get('#board-project').setValue('p1')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(create).toHaveBeenCalledWith({ title: 'Fluxo de pedidos', project_id: 'p1' })
    expect(router.currentRoute.value.fullPath).toBe('/boards/novo')
  })
})
