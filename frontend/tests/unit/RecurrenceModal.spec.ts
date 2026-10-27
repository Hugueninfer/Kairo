import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RecurrenceModal from '@/components/billing/RecurrenceModal.vue'
import { projectsApi } from '@/services/projects'
import { contractsApi } from '@/services/quotes'
import type { Contract, Options } from '@/types/api'

const contract: Contract = {
  id: 'c1',
  number: 'CTR-2026-0001',
  title: 'Manutenção do site',
  status: { value: 'active', label: 'Vigente' },
  total: '6000.00',
  terms: null,
  signed_at: '2026-10-01T12:00:00Z',
  signer_name: 'Maria',
  recurrence: null,
}

describe('RecurrenceModal', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    setActivePinia(createPinia())
    vi.spyOn(projectsApi, 'options').mockResolvedValue({
      recurrence_frequencies: [
        { value: 'monthly', label: 'Mensal' },
        { value: 'yearly', label: 'Anual' },
      ],
    } as Options)
  })

  it('liga a recorrência com valor em vírgula e sem envio automático', async () => {
    const saved = { ...contract, recurrence: null }
    const spy = vi.spyOn(contractsApi, 'recurrence').mockResolvedValue(saved)
    const wrapper = mount(RecurrenceModal, { props: { contract } })
    await flushPromises()

    await wrapper.get('#recurrence-frequency').setValue('yearly')
    await wrapper.get('#recurrence-amount').setValue('1200,50')
    await wrapper.get('#recurrence-start').setValue('2026-11-01')
    await wrapper.get('[data-test="auto-send"]').setValue(false)
    expect(wrapper.text()).toContain('A fatura fica em rascunho')
    expect(wrapper.find('[data-test="turn-off"]').exists()).toBe(false)

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith('c1', {
      frequency: 'yearly',
      amount: '1200.50',
      starts_on: '2026-11-01',
      ends_on: null,
      payment_term_days: 7,
      auto_send: false,
    })
    expect(wrapper.emitted('saved')?.[0]).toEqual([saved])
  })

  it('preenche a recorrência existente e deixa desligar', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const spy = vi.spyOn(contractsApi, 'recurrence').mockResolvedValue(contract)
    const wrapper = mount(RecurrenceModal, {
      props: {
        contract: {
          ...contract,
          recurrence: {
            frequency: { value: 'monthly', label: 'Mensal' },
            amount: '500.00',
            next_billing_on: '2026-11-27',
            ends_on: null,
            payment_term_days: 5,
            auto_send: true,
          },
        },
      },
    })
    await flushPromises()

    expect((wrapper.get('#recurrence-amount').element as HTMLInputElement).value).toBe('500.00')
    expect((wrapper.get('#recurrence-start').element as HTMLInputElement).value).toBe('2026-11-27')

    await wrapper.get('[data-test="turn-off"]').trigger('click')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith('c1', { frequency: null })
  })
})
