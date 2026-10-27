import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { publicQuoteApi } from '@/services/quotes'
import type { PublicQuote } from '@/types/api'
import PublicQuoteView from '@/views/quotes/PublicQuoteView.vue'

function publicQuote(extra: Partial<PublicQuote> = {}): PublicQuote {
  return {
    number: 'ORC-2026-0001',
    title: 'Site institucional',
    status: { value: 'viewed', label: 'Visualizado' },
    awaits_decision: true,
    valid_until: '2026-11-10',
    introduction: null,
    terms: null,
    subtotal: '1000.00',
    discount: '0.00',
    tax_rate: '0.00',
    tax_amount: '0.00',
    total: '1000.00',
    currency: 'BRL',
    decided_at: null,
    signer_name: null,
    company: { name: 'Estúdio Lua' },
    client: { name: 'Padaria' },
    items: [{ description: 'Layout', type: { value: 'fixed', label: 'Valor fixo', unit: 'un.' }, quantity: '1.00', unit_price: '1000.00', total: '1000.00' }],
    ...extra,
  }
}

async function mountView() {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/orcamento/:id', component: PublicQuoteView }] })
  await router.push('/orcamento/01abc?expires=123&signature=xyz')
  const wrapper = mount(PublicQuoteView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('PublicQuoteView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('repassa a assinatura do link e aceita com o nome digitado', async () => {
    const get = vi.spyOn(publicQuoteApi, 'get').mockResolvedValue(publicQuote())
    const decide = vi.spyOn(publicQuoteApi, 'decide').mockResolvedValue(
      publicQuote({ status: { value: 'accepted', label: 'Aceito' }, awaits_decision: false, signer_name: 'Maria', decided_at: '2026-10-27T15:00:00Z' }),
    )

    const wrapper = await mountView()
    expect(get).toHaveBeenCalledWith('01abc', { expires: '123', signature: 'xyz' })
    expect(wrapper.get('[data-test="public-total"]').text()).toContain('1.000,00')

    await wrapper.findAll('button').find((b) => b.text() === 'Aceitar orçamento')!.trigger('click')
    await wrapper.get('#signer-name').setValue('Maria')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(decide).toHaveBeenCalledWith('01abc', { expires: '123', signature: 'xyz' }, { decision: 'accept', signer_name: 'Maria' })
    expect(wrapper.get('[data-test="decision"]').text()).toContain('aceito por Maria')
  })

  it('não oferece resposta depois da validade', async () => {
    vi.spyOn(publicQuoteApi, 'get').mockResolvedValue(publicQuote({ awaits_decision: false }))

    const wrapper = await mountView()
    expect(wrapper.get('[data-test="decision"]').text()).toContain('A validade deste orçamento terminou')
    expect(wrapper.text()).not.toContain('Aceitar orçamento')
  })
})
