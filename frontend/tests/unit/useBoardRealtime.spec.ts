import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { useBoardRealtime, type BoardChangedEvent } from '@/composables/useBoardRealtime'

// Echo de mentira: guarda os callbacks do canal para os testes dispararem os eventos.
const handlers: Record<string, (payload: never) => void> = {}
const whisper = vi.fn()
const leave = vi.fn()
const stateHandlers: ((states: { previous: string; current: string }) => void)[] = []

const channel = {
  here: (cb: never) => ((handlers.here = cb), channel),
  joining: (cb: never) => ((handlers.joining = cb), channel),
  leaving: (cb: never) => ((handlers.leaving = cb), channel),
  error: (cb: never) => ((handlers.error = cb), channel),
  listen: (event: string, cb: never) => ((handlers[event] = cb), channel),
  listenForWhisper: (event: string, cb: never) => ((handlers[`whisper:${event}`] = cb), channel),
  whisper,
}

vi.mock('@/services/realtime', () => ({
  realtime: () => ({
    join: vi.fn(() => channel),
    leave,
    connector: {
      pusher: {
        connection: {
          bind: (_: string, cb: (states: { previous: string; current: string }) => void) => stateHandlers.push(cb),
          unbind: vi.fn(),
        },
      },
    },
  }),
}))

function fire(name: string, payload: unknown) {
  handlers[name]!(payload as never)
}

function setup() {
  const onChanged = vi.fn<(event: BoardChangedEvent) => void>()
  const onResync = vi.fn()
  let live!: ReturnType<typeof useBoardRealtime>
  const wrapper = mount(
    defineComponent({
      setup() {
        live = useBoardRealtime('b1', { id: 1 }, { onChanged, onResync })
        return () => h('div')
      },
    }),
  )
  return { wrapper, live, onChanged, onResync }
}

describe('useBoardRealtime', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    whisper.mockClear()
    leave.mockClear()
    stateHandlers.length = 0
  })

  afterEach(() => vi.useRealTimers())

  it('acompanha quem entra e sai e mostra o cursor de quem está no board', () => {
    const { live } = setup()
    expect(live.status.value).toBe('connecting')

    fire('here', [{ id: 1, name: 'Eu', can_edit: true }])
    fire('joining', { id: 2, name: 'Ana Souza', can_edit: true })
    expect(live.status.value).toBe('online')
    expect(live.members.value.map((member) => member.id)).toEqual([1, 2])

    fire('whisper:cursor', { id: 2, x: 120, y: -40 })
    fire('whisper:cursor', { id: 99, x: 1, y: 1 })
    expect([...live.cursors.values()]).toEqual([expect.objectContaining({ id: 2, name: 'Ana Souza', x: 120, y: -40 })])

    fire('leaving', { id: 2, name: 'Ana Souza', can_edit: true })
    expect(live.members.value.map((member) => member.id)).toEqual([1])
    expect(live.cursors.size).toBe(0)
  })

  it('manda o próprio cursor no máximo a cada 50 ms', () => {
    const { live } = setup()

    live.moveCursor({ x: 10.4, y: 20.6 })
    live.moveCursor({ x: 11, y: 21 })
    live.moveCursor({ x: 12, y: 22 })
    expect(whisper).toHaveBeenCalledTimes(1)
    expect(whisper).toHaveBeenLastCalledWith('cursor', { id: 1, x: 10, y: 21 })

    vi.advanceTimersByTime(50)
    expect(whisper).toHaveBeenCalledTimes(2)
    expect(whisper).toHaveBeenLastCalledWith('cursor', { id: 1, x: 12, y: 22 })

    vi.advanceTimersByTime(50)
    live.moveCursor(null)
    expect(whisper).toHaveBeenLastCalledWith('cursor', { id: 1, gone: true })
  })

  it('repassa as alterações salvas e recarrega depois de perder a conexão', () => {
    const { live, onChanged, onResync, wrapper } = setup()
    const event = { editor: { id: 2, name: 'Ana' }, reload: false, upserts: [], deletes: ['x'] }

    fire('.elements.changed', event)
    expect(onChanged).toHaveBeenCalledWith(event)
    expect(() => fire('.version.saved', { id: 'v1' })).not.toThrow()

    stateHandlers.forEach((cb) => cb({ previous: 'connected', current: 'unavailable' }))
    expect(live.status.value).toBe('offline')
    stateHandlers.forEach((cb) => cb({ previous: 'unavailable', current: 'connected' }))
    expect(onResync).toHaveBeenCalledTimes(1)

    wrapper.unmount()
    expect(leave).toHaveBeenCalledWith('board.b1')
  })
})
