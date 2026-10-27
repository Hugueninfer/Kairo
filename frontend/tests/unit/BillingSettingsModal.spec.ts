import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import BillingSettingsModal from '@/components/billing/BillingSettingsModal.vue'
import { invoicesApi } from '@/services/invoices'
import { projectsApi } from '@/services/projects'
import { useCompanyStore } from '@/stores/company'
import type { Options } from '@/types/api'

async function mountModal(permissions: string[]) {
  setActivePinia(createPinia())
  useCompanyStore().access = { role: null, permissions }
  const wrapper = mount(BillingSettingsModal)
  await flushPromises()
  return wrapper
}

describe('BillingSettingsModal', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.spyOn(projectsApi, 'options').mockResolvedValue({
      reminder_stages: [
        { value: 'before_due', label: '3 dias antes do vencimento' },
        { value: 'due_today', label: 'No dia do vencimento' },
        { value: 'overdue_3', label: '3 dias depois do vencimento' },
      ],
    } as Options)
    vi.spyOn(invoicesApi, 'billingSettings').mockResolvedValue({
      reminders_enabled: true,
      reminder_stages: ['before_due', 'due_today'],
      reminder_message: null,
    })
  })

  it('marca as etapas salvas e envia a escolha nova', async () => {
    const spy = vi.spyOn(invoicesApi, 'updateBillingSettings').mockImplementation(async (payload) => payload)
    const wrapper = await mountModal(['invoices.view', 'invoices.manage'])

    const boxes = wrapper.get('[data-test="stages"]').findAll('input')
    expect(boxes.map((box) => (box.element as HTMLInputElement).checked)).toEqual([true, true, false])

    await boxes[0]!.setValue(false)
    await boxes[2]!.setValue(true)
    await wrapper.get('#reminder-message').setValue('  Pix com desconto.  ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({
      reminders_enabled: true,
      reminder_stages: ['due_today', 'overdue_3'],
      reminder_message: 'Pix com desconto.',
    })
    expect(wrapper.text()).toContain('Régua de lembretes salva.')
  })

  it('quem só vê faturas não altera a régua', async () => {
    const wrapper = await mountModal(['invoices.view'])

    expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).not.toContain('Salvar')
  })
})
