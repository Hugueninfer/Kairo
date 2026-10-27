import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import BoardVersionsPanel from '@/components/boards/BoardVersionsPanel.vue'
import { boardsApi } from '@/services/boards'
import type { BoardVersion } from '@/types/api'

const versions: BoardVersion[] = [
  { id: 'v2', label: 'Antes da reunião', automatic: false, element_count: 3, created_at: '2026-10-27T15:00:00Z', author: { name: 'Ana' } },
  { id: 'v1', label: null, automatic: true, element_count: 1, created_at: '2026-10-27T14:00:00Z', author: null },
]

describe('BoardVersionsPanel', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.spyOn(boardsApi, 'versions').mockResolvedValue({ data: versions, meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 } })
  })

  it('lista as versões e abre a escolhida para ver', async () => {
    const opened = { ...versions[1]!, elements: [] }
    vi.spyOn(boardsApi, 'version').mockResolvedValue(opened)
    const wrapper = mount(BoardVersionsPanel, { props: { boardId: 'b1', editable: true, previewing: null } })
    await flushPromises()

    const items = wrapper.findAll('[data-test="version"]')
    expect(items.map((item) => item.text())).toEqual([
      expect.stringContaining('Antes da reunião'),
      expect.stringContaining('Versão automática'),
    ])
    expect(items[0]!.text()).toContain('Ana · 3 elementos')

    await items[1]!.trigger('click')
    await flushPromises()
    expect(boardsApi.version).toHaveBeenCalledWith('b1', 'v1')
    expect(wrapper.emitted('preview')?.[0]).toEqual([opened])
  })

  it('salva versão com nome e esconde o formulário de quem só vê', async () => {
    vi.spyOn(boardsApi, 'saveVersion').mockResolvedValue()
    const wrapper = mount(BoardVersionsPanel, { props: { boardId: 'b1', editable: true, previewing: null } })
    await flushPromises()

    await wrapper.get('input[aria-label="Nome da versão"]').setValue('  Entrega 1 ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(boardsApi.saveVersion).toHaveBeenCalledWith('b1', 'Entrega 1')
    expect(wrapper.text()).toContain('Versão salva.')

    const viewer = mount(BoardVersionsPanel, { props: { boardId: 'b1', editable: false, previewing: null } })
    await flushPromises()
    expect(viewer.find('[data-test="save-version"]').exists()).toBe(false)
  })
})
