import { computed, reactive } from 'vue'
import type { BoardChanges } from '@/services/boards'
import type { BoardElement, BoardElementType } from '@/types/api'

const CROCKFORD = '0123456789abcdefghjkmnpqrstvwxyz'

/**
 * ULID gerado no navegador: o elemento já nasce com o id definitivo, sem esperar a API.
 */
export function ulid(now = Date.now()): string {
  let time = ''
  for (let i = 0; i < 10; i++) {
    time = CROCKFORD[now % 32] + time
    now = Math.floor(now / 32)
  }
  const random = crypto.getRandomValues(new Uint8Array(16))
  return time + Array.from(random, (byte) => CROCKFORD[byte % 32]).join('')
}

export const BOARD_COLORS = ['#FEF08A', '#FBCFE8', '#BFDBFE', '#BBF7D0', '#FED7AA', '#E2E8F0'] as const
export const STROKE_COLORS = ['#334155', '#B91C1C', '#1D4ED8', '#15803D', '#C2410C', '#7C3AED'] as const

/**
 * Elemento novo com tamanho e cores padrão, centralizado no ponto clicado.
 */
export function newElement(type: Exclude<BoardElementType, 'arrow'>, x: number, y: number, zIndex: number): BoardElement {
  const base = { id: ulid(), type, rotation: 0, z_index: zIndex }
  const sized = (width: number, height: number) => ({ x: Math.round(x - width / 2), y: Math.round(y - height / 2), width, height })

  switch (type) {
    case 'note':
      return { ...base, ...sized(200, 160), props: { text: '', fill: '#FEF08A' } }
    case 'rect':
      return { ...base, ...sized(180, 110), props: { text: '', fill: '#BFDBFE', stroke: '#1D4ED8' } }
    case 'ellipse':
      return { ...base, ...sized(160, 110), props: { text: '', fill: '#BBF7D0', stroke: '#15803D' } }
    case 'text':
      return { ...base, ...sized(260, 40), props: { text: 'Texto', font_size: 24, fill: '#0F172A' } }
  }
}

export function newArrow(from: BoardElement, to: BoardElement, zIndex: number): BoardElement {
  return {
    id: ulid(),
    type: 'arrow',
    x: 0,
    y: 0,
    width: 0,
    height: 0,
    rotation: 0,
    z_index: zIndex,
    props: { from: from.id, to: to.id, stroke: '#334155', points: arrowPoints(from, to) },
  }
}

interface Box {
  x: number
  y: number
  width: number
  height: number
}

/**
 * Ponto onde a reta que sai do centro da caixa em direção a (tx, ty) cruza a borda.
 */
function edgePoint(box: Box, tx: number, ty: number): [number, number] {
  const cx = box.x + box.width / 2
  const cy = box.y + box.height / 2
  const dx = tx - cx
  const dy = ty - cy
  if (dx === 0 && dy === 0) return [cx, cy]
  const scale = Math.min(
    dx === 0 ? Infinity : box.width / 2 / Math.abs(dx),
    dy === 0 ? Infinity : box.height / 2 / Math.abs(dy),
  )
  return [cx + dx * scale, cy + dy * scale]
}

/**
 * Pontos da seta entre dois elementos: da borda de um à borda do outro, na linha dos centros.
 */
export function arrowPoints(from: Box, to: Box): number[] {
  const [x1, y1] = edgePoint(from, to.x + to.width / 2, to.y + to.height / 2)
  const [x2, y2] = edgePoint(to, from.x + from.width / 2, from.y + from.height / 2)
  return [x1, y1, x2, y2].map((value) => Math.round(value * 10) / 10)
}

/**
 * Estado do canvas com controle do que mudou desde o último autosave.
 */
export function useBoardDocument(initial: BoardElement[] = []) {
  const elements = reactive(new Map<string, BoardElement>(initial.map((element) => [element.id, element])))
  const dirty = new Set<string>()
  const deleted = new Set<string>()

  const ordered = computed(() => [...elements.values()].sort((a, b) => a.z_index - b.z_index))
  const topZ = computed(() => ordered.value.reduce((max, element) => Math.max(max, element.z_index), 0))

  function get(id: string): BoardElement | undefined {
    return elements.get(id)
  }

  /** As setas ligadas acompanham o elemento que se mexeu. */
  function refreshArrows(movedId: string) {
    for (const arrow of elements.values()) {
      if (arrow.type !== 'arrow' || (arrow.props.from !== movedId && arrow.props.to !== movedId)) continue
      const from = elements.get(arrow.props.from ?? '')
      const to = elements.get(arrow.props.to ?? '')
      if (!from || !to) continue
      arrow.props = { ...arrow.props, points: arrowPoints(from, to) }
      dirty.add(arrow.id)
    }
  }

  function upsert(element: BoardElement) {
    elements.set(element.id, element)
    deleted.delete(element.id)
    dirty.add(element.id)
    refreshArrows(element.id)
  }

  function update(id: string, patch: Partial<Omit<BoardElement, 'id' | 'type'>>) {
    const current = elements.get(id)
    if (!current) return
    upsert({ ...current, ...patch, props: { ...current.props, ...(patch.props ?? {}) } })
  }

  /** Apaga o elemento e as setas presas a ele. */
  function remove(id: string) {
    const ids = [id]
    for (const element of elements.values()) {
      if (element.type === 'arrow' && (element.props.from === id || element.props.to === id)) ids.push(element.id)
    }
    for (const item of ids) {
      elements.delete(item)
      dirty.delete(item)
      deleted.add(item)
    }
  }

  function bringToFront(id: string) {
    update(id, { z_index: topZ.value + 1 })
  }

  const hasChanges = () => dirty.size > 0 || deleted.size > 0

  /** Pega o lote para enviar e limpa as pendências. */
  function takeChanges(): BoardChanges {
    const changes: BoardChanges = {
      upserts: [...dirty].map((id) => elements.get(id)).filter((element): element is BoardElement => element !== undefined),
      deletes: [...deleted],
    }
    dirty.clear()
    deleted.clear()
    return changes
  }

  /** Se o envio falhar, o lote volta a ficar pendente (sem passar por cima de mudanças mais novas). */
  function restore(changes: BoardChanges) {
    for (const element of changes.upserts) if (elements.has(element.id)) dirty.add(element.id)
    for (const id of changes.deletes) if (!elements.has(id)) deleted.add(id)
  }

  return { elements, ordered, topZ, get, upsert, update, remove, bringToFront, hasChanges, takeChanges, restore }
}

export type BoardDocument = ReturnType<typeof useBoardDocument>
