import type { BadgeTone } from '@/components/ui/StatusBadge.vue'

/** Cor da pílula de cada situação, a mesma em todas as telas. */
const tones: Record<string, Record<string, BadgeTone>> = {
  project: { active: 'blue', paused: 'amber', completed: 'green', archived: 'muted' },
  task: { todo: 'neutral', in_progress: 'blue', review: 'violet', done: 'green' },
  timeEntry: { pending: 'amber', approved: 'green', rejected: 'red' },
  quote: { draft: 'neutral', sent: 'blue', viewed: 'violet', accepted: 'green', rejected: 'red', expired: 'amber' },
  contract: { active: 'blue', completed: 'green', cancelled: 'muted' },
  invoice: { draft: 'neutral', open: 'blue', partially_paid: 'violet', overdue: 'red', paid: 'green', cancelled: 'muted' },
}

export function statusTone(kind: keyof typeof tones, value: string): BadgeTone {
  return tones[kind]?.[value] ?? 'neutral'
}
