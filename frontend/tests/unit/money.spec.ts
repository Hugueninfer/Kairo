import { describe, expect, it } from 'vitest'
import { formatMoney, fromCents, quoteTotals, toCents } from '@/composables/money'

describe('dinheiro', () => {
  it('converte texto em centavos', () => {
    expect(toCents('12.5')).toBe(1250)
    expect(toCents('12,05')).toBe(1205)
    expect(toCents('7')).toBe(700)
    expect(toCents('abc')).toBe(0)
    expect(toCents('1.234')).toBe(0)
    expect(fromCents(123405)).toBe('1234.05')
  })

  it('formata em reais', () => {
    expect(formatMoney('4028').replace(/\s/g, ' ')).toBe('R$ 4.028,00')
  })

  it('calcula os totais como o back-end', () => {
    const totals = quoteTotals(
      [
        { quantity: '1', unit_price: '1500' },
        { quantity: '20', unit_price: '120' },
      ],
      '100',
      '6',
    )
    expect(totals.lines).toEqual(['1500.00', '2400.00'])
    expect(totals.subtotal).toBe('3900.00')
    expect(totals.tax).toBe('228.00')
    expect(totals.total).toBe('4028.00')
    expect(totals.discountTooHigh).toBe(false)
  })

  it('arredonda meio centavo para cima e avisa desconto alto', () => {
    expect(quoteTotals([{ quantity: '0.5', unit_price: '0.05' }], '0', '0').total).toBe('0.03')
    expect(quoteTotals([{ quantity: '1', unit_price: '10' }], '10.01', '0').discountTooHigh).toBe(true)
  })
})
