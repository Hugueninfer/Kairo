import { describe, expect, it } from 'vitest'
import { addDays, formatElapsed, formatMinutes, parseDuration } from '@/composables/time'

describe('horas', () => {
  it('formata minutos como h:mm', () => {
    expect(formatMinutes(0)).toBe('0:00')
    expect(formatMinutes(90)).toBe('1:30')
    expect(formatMinutes(605)).toBe('10:05')
  })

  it('formata o timer com segundos', () => {
    expect(formatElapsed(3725)).toBe('1:02:05')
    expect(formatElapsed(-4)).toBe('0:00:00')
  })

  it.each([
    ['1:30', 90],
    ['1h30', 90],
    ['2h', 120],
    ['1,5', 90],
    ['0.25', 15],
    ['45m', 45],
    ['45min', 45],
  ])('entende "%s" como %i minutos', (input, minutes) => {
    expect(parseDuration(input)).toBe(minutes)
  })

  it.each(['', 'abc', '1:75', '0', '-1', '0:00'])('recusa "%s"', (input) => {
    expect(parseDuration(input)).toBeNull()
  })

  it('soma dias sem depender do fuso', () => {
    expect(addDays('2026-10-26', 7)).toBe('2026-11-02')
    expect(addDays('2026-03-01', -1)).toBe('2026-02-28')
  })
})
