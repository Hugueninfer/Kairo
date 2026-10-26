/** 90 → "1:30" */
export function formatMinutes(minutes: number): string {
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  return `${hours}:${String(rest).padStart(2, '0')}`
}

/** Segundos corridos → "1:02:05", para o timer. */
export function formatElapsed(seconds: number): string {
  const safe = Math.max(0, Math.floor(seconds))
  const h = Math.floor(safe / 3600)
  const m = Math.floor((safe % 3600) / 60)
  const s = safe % 60
  return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

/**
 * Lê a duração digitada: "1:30", "1h30", "1,5", "1.5" ou "90m". Devolve minutos ou null se não entender.
 */
export function parseDuration(input: string): number | null {
  const text = input.trim().toLowerCase().replace(/\s+/g, '')
  if (text === '') return null

  const clock = text.match(/^(\d{1,2})(?::|h)(\d{1,2})?m?$/)
  if (clock) {
    const minutes = Number(clock[1]) * 60 + Number(clock[2] ?? 0)
    return Number(clock[2] ?? 0) < 60 && minutes > 0 ? minutes : null
  }

  const onlyMinutes = text.match(/^(\d{1,4})m(in)?$/)
  if (onlyMinutes) {
    const minutes = Number(onlyMinutes[1])
    return minutes > 0 ? minutes : null
  }

  const hours = Number(text.replace(',', '.'))
  if (Number.isFinite(hours) && hours > 0) return Math.round(hours * 60)

  return null
}

/** Data local (YYYY-MM-DD) sem passar por UTC. */
export function isoDate(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

/** Soma dias a uma data YYYY-MM-DD. */
export function addDays(day: string, amount: number): string {
  const [y, m, d] = day.split('-').map(Number)
  return isoDate(new Date(y!, m! - 1, d! + amount))
}
