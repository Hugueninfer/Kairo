import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { clientsApi } from '@/services/clients'
import { invoicesApi } from '@/services/invoices'
import { projectsApi } from '@/services/projects'
import { useCompanyStore } from '@/stores/company'
import type { Invoice, Options } from '@/types/api'
import InvoiceView from '@/views/billing/InvoiceView.vue'

function openInvoice(extra: Partial<Invoice> = {}): Invoice {
  return {
    id: 'inv1',
    number: 'FAT-2026-0001',
    status: { value: 'open', label: 'Em aberto', awaits_payment: true },
    issue_date: '2026-10-27',
    due_date: '2026-11-10',
    notes: null,
    subtotal: '1000.00',
    discount: '0.00',
    total: '1000.00',
    amount_paid: '0.00',
    payment_url: 'https://pagar.test/abc',
    sent_at: '2026-10-27T15:00:00Z',
    paid_at: null,
    cancelled_at: null,
    client: { id: 'c1', name: 'Padaria', email: 'p@test' },
    project: null,
    contract: null,
    items: [{ description: 'Serviço', quantity: '1.00', unit_price: '1000.00', total: '1000.00' }],
    payments: [],
    ...extra,
  }
}

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/faturas/:id', name: 'invoice', component: InvoiceView },
      { path: '/faturas', name: 'invoices', component: { template: '<div />' } },
    ],
  })
  await router.push('/faturas/inv1')
  const wrapper = mount(InvoiceView, { global: { plugins: [router], stubs: { AppLayout: { template: '<div><slot /></div>' } } } })
  await flushPromises()
  return wrapper
}

describe('InvoiceView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
    useCompanyStore().access = { role: null, permissions: ['invoices.view', 'invoices.manage'] }
    vi.spyOn(clientsApi, 'list').mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, per_page: 100, total: 0 } })
    vi.spyOn(projectsApi, 'list').mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, per_page: 100, total: 0 } })
    vi.spyOn(projectsApi, 'options').mockResolvedValue({ payment_methods: [{ value: 'pix', label: 'Pix' }] } as Options)
  })

  it('mostra o link de pagamento e o saldo da fatura enviada, sem campos editáveis', async () => {
    vi.spyOn(invoicesApi, 'get').mockResolvedValue(openInvoice({ amount_paid: '400.00' }))

    const wrapper = await mountView()

    expect(wrapper.get('[data-test="payment-url"]').attributes('href')).toBe('https://pagar.test/abc')
    expect(wrapper.get('[data-test="payment-box"]').text()).toContain('600,00')
    expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-test="send"]').exists()).toBe(false)
  })

  it('registra pagamento manual com o saldo sugerido', async () => {
    vi.spyOn(invoicesApi, 'get').mockResolvedValue(openInvoice({ amount_paid: '400.00' }))
    const record = vi.spyOn(invoicesApi, 'recordPayment').mockResolvedValue(
      openInvoice({ status: { value: 'paid', label: 'Paga', awaits_payment: false }, amount_paid: '1000.00', paid_at: '2026-10-27T15:00:00Z' }),
    )

    const wrapper = await mountView()
    expect((wrapper.get('#payment-amount').element as HTMLInputElement).value).toBe('600.00')
    await wrapper.get('[data-test="payment-box"] + div form').trigger('submit')
    await flushPromises()

    expect(record).toHaveBeenCalledWith('inv1', expect.objectContaining({ amount: '600.00', method: 'pix' }))
    expect(wrapper.get('[data-test="invoice-status"]').text()).toBe('Paga')
  })
})
