/** "12.5" → 1250 centavos. Vazio ou inválido vira 0. */
export function toCents(value: string | number): number {
  const text = String(value).trim().replace(',', '.')
  if (!/^\d+(\.\d{0,2})?$/.test(text)) return 0
  const [whole = '0', fraction = ''] = text.split('.')
  return Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
}

export function fromCents(cents: number): string {
  return `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`
}

export function formatMoney(value: string | number, currency = 'BRL'): string {
  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency })
}

export interface QuoteDraftItem {
  quantity: string
  unit_price: string
}

/**
 * Prévia dos totais enquanto a pessoa edita. Mesma conta do back-end (centavos, meio
 * centavo para cima); o valor que vale é o que a API grava.
 */
export function quoteTotals(items: QuoteDraftItem[], discount: string, taxRate: string) {
  const lines = items.map((item) => Math.floor((toCents(item.quantity) * toCents(item.unit_price) + 50) / 100))
  const subtotal = lines.reduce((sum, line) => sum + line, 0)
  const discountCents = toCents(discount)
  const base = Math.max(0, subtotal - discountCents)
  const tax = Math.floor((base * toCents(taxRate) + 5000) / 10000)

  return {
    lines: lines.map(fromCents),
    subtotal: fromCents(subtotal),
    discountTooHigh: discountCents > subtotal,
    tax: fromCents(tax),
    total: fromCents(base + tax),
  }
}
