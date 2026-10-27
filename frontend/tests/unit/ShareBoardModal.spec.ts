import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ShareBoardModal from '@/components/boards/ShareBoardModal.vue'
import { boardsApi } from '@/services/boards'
import type { Board } from '@/types/api'

const board: Board = { id: 'b1', title: 'Mapa do site', updated_at: '2026-10-27T15:00:00Z', share_url: null }

describe('ShareBoardModal', () => {
  beforeEach(() => vi.restoreAllMocks())

  it('cria o link de leitura e avisa o board', async () => {
    const shared = { ...board, share_url: 'http://localhost:5173/b/abc' }
    vi.spyOn(boardsApi, 'share').mockResolvedValue(shared)
    const wrapper = mount(ShareBoardModal, { props: { board } })

    await wrapper.get('[data-test="share-on"]').trigger('click')
    await flushPromises()

    expect(boardsApi.share).toHaveBeenCalledWith('b1')
    expect(wrapper.emitted('updated')?.[0]).toEqual([shared])
  })

  it('mostra o link e desliga depois de confirmar', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.spyOn(boardsApi, 'unshare').mockResolvedValue(board)
    const wrapper = mount(ShareBoardModal, { props: { board: { ...board, share_url: 'http://localhost:5173/b/abc' } } })

    expect((wrapper.get('[data-test="share-url"]').element as HTMLInputElement).value).toBe('http://localhost:5173/b/abc')
    await wrapper.get('[data-test="share-off"]').trigger('click')
    await flushPromises()

    expect(boardsApi.unshare).toHaveBeenCalledWith('b1')
    expect(wrapper.emitted('updated')?.[0]).toEqual([board])
  })
})
