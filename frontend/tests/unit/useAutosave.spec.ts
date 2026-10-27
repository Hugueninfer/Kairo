import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { useAutosave, type AutosaveOptions } from '@/composables/useAutosave'

function setup(save: () => Promise<void>, hasChanges: () => boolean, options?: AutosaveOptions) {
  let autosave!: ReturnType<typeof useAutosave>
  mount(
    defineComponent({
      setup() {
        autosave = useAutosave(save, hasChanges, options)
        return () => null
      },
    }),
  )
  return autosave
}

describe('useAutosave', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('junta várias mudanças em um envio só', async () => {
    let pending = true
    const save = vi.fn(async () => {
      pending = false
    })
    const autosave = setup(save, () => pending)

    autosave.schedule()
    autosave.schedule()
    expect(autosave.state.value).toBe('pending')
    await vi.advanceTimersByTimeAsync(800)

    expect(save).toHaveBeenCalledTimes(1)
    expect(autosave.state.value).toBe('saved')
  })

  it('tenta de novo quando a rede falha e para quando a API recusa', async () => {
    let pending = true
    const save = vi.fn(async () => {
      if (save.mock.calls.length === 1) throw new Error('offline')
      pending = false
    })
    const autosave = setup(save, () => pending, { retryDelay: 1000 })

    await autosave.flush()
    expect(autosave.state.value).toBe('error')
    await vi.advanceTimersByTimeAsync(1000)
    expect(save).toHaveBeenCalledTimes(2)
    expect(autosave.state.value).toBe('saved')

    const rejected = vi.fn(async () => {
      throw new Error('422')
    })
    const fatal = setup(rejected, () => true, { retryDelay: 1000, isFatal: () => true })
    await fatal.flush()
    await vi.advanceTimersByTimeAsync(5000)
    expect(rejected).toHaveBeenCalledTimes(1)
    expect(fatal.state.value).toBe('error')
  })
})
