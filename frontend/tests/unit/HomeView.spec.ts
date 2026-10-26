import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import HomeView from '@/views/HomeView.vue'
import * as health from '@/services/health'

describe('HomeView', () => {
  it('mostra o status da API quando ela responde', async () => {
    vi.spyOn(health, 'fetchHealth').mockResolvedValue({ status: 'ok', app: 'Kairo', version: '0.1.0' })

    const wrapper = mount(HomeView)
    await flushPromises()

    expect(wrapper.find('[data-test="api-ok"]').text()).toContain('ok')
  })

  it('mostra erro quando a API não responde', async () => {
    vi.spyOn(health, 'fetchHealth').mockRejectedValue(new Error('offline'))

    const wrapper = mount(HomeView)
    await flushPromises()

    expect(wrapper.find('[data-test="api-error"]').exists()).toBe(true)
  })
})
