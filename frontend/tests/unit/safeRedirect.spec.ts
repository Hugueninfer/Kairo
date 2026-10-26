import { describe, expect, it } from 'vitest'
import { safeRedirect } from '@/composables/safeRedirect'

describe('safeRedirect', () => {
  it('aceita caminhos internos', () => {
    expect(safeRedirect('/convite/abc')).toBe('/convite/abc')
  })

  it('recusa URLs externas e caminhos com duas barras', () => {
    expect(safeRedirect('https://site-malicioso.com')).toBe('/')
    expect(safeRedirect('//site-malicioso.com')).toBe('/')
    expect(safeRedirect(undefined)).toBe('/')
  })
})
