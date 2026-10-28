import { describe, expect, it } from 'vitest'
import { arrowPoints, newArrow, newElement, ulid, useBoardDocument } from '@/composables/boardDocument'

describe('ulid', () => {
  it('gera 26 caracteres em minúsculas, ordenáveis pelo tempo', () => {
    const first = ulid(1_700_000_000_000)
    const second = ulid(1_700_000_000_001)

    expect(first).toMatch(/^[0-9a-hjkmnp-tv-z]{26}$/)
    expect(first.slice(0, 10) < second.slice(0, 10)).toBe(true)
  })
})

describe('arrowPoints', () => {
  it('liga as bordas dos dois elementos na linha dos centros', () => {
    const left = { x: 0, y: 0, width: 100, height: 100 }
    const right = { x: 300, y: 0, width: 100, height: 100 }

    expect(arrowPoints(left, right)).toEqual([100, 50, 300, 50])
    expect(arrowPoints({ x: 0, y: 0, width: 100, height: 50 }, { x: 0, y: 200, width: 100, height: 50 })).toEqual([50, 50, 50, 200])
  })
})

describe('useBoardDocument', () => {
  it('centraliza o elemento novo no clique e ordena pela camada', () => {
    const note = newElement('note', 500, 300, 2)
    const text = newElement('text', 0, 0, 1)
    const doc = useBoardDocument([note, text])

    expect(note).toMatchObject({ x: 400, y: 220, width: 200, height: 160, props: { fill: '#FEF08A' } })
    expect(doc.ordered.value.map((element) => element.id)).toEqual([text.id, note.id])
    expect(doc.topZ.value).toBe(2)
  })

  it('só envia o que mudou e a seta acompanha o elemento', () => {
    const a = newElement('rect', 50, 50, 1)
    const b = newElement('rect', 450, 50, 2)
    const arrow = newArrow(a, b, 3)
    const doc = useBoardDocument([a, b, arrow])
    expect(doc.hasChanges()).toBe(false)

    doc.update(b.id, { y: 300 })
    const changes = doc.takeChanges()

    expect(changes.deletes).toEqual([])
    expect(changes.upserts.map((element) => element.id).sort()).toEqual([b.id, arrow.id].sort())
    expect(doc.get(arrow.id)?.props.points).toEqual(arrowPoints(a, { ...b, y: 300 }))
    expect(doc.hasChanges()).toBe(false)
  })

  it('apagar um elemento apaga as setas presas a ele', () => {
    const a = newElement('note', 0, 0, 1)
    const b = newElement('note', 400, 0, 2)
    const arrow = newArrow(a, b, 3)
    const doc = useBoardDocument([a, b, arrow])

    doc.remove(a.id)

    expect(doc.elements.size).toBe(1)
    expect(doc.takeChanges().deletes.sort()).toEqual([a.id, arrow.id].sort())
  })

  it('devolve o lote como pendente quando o envio falha', () => {
    const doc = useBoardDocument()
    const note = newElement('note', 0, 0, 1)
    doc.upsert(note)

    const changes = doc.takeChanges()
    doc.restore(changes)

    expect(doc.takeChanges().upserts.map((element) => element.id)).toEqual([note.id])
  })
})

describe('alterações de outras pessoas', () => {
  it('aplica o que chegou sem virar pendência e sem passar por cima do que não foi salvo aqui', () => {
    const mine = newElement('note', 0, 0, 1)
    const theirs = newElement('rect', 300, 0, 2)
    const gone = newElement('ellipse', 600, 0, 3)
    const doc = useBoardDocument([mine, theirs, gone])

    doc.update(mine.id, { x: 50 })
    doc.takeChanges()
    doc.update(mine.id, { x: 70 })

    const created = newElement('text', 0, 400, 4)
    doc.applyRemote(
      [
        { ...mine, x: 999 },
        { ...theirs, x: 333 },
        created,
      ],
      [gone.id],
    )

    expect(doc.get(mine.id)?.x).toBe(70)
    expect(doc.get(theirs.id)?.x).toBe(333)
    expect(doc.get(created.id)).toBeDefined()
    expect(doc.get(gone.id)).toBeUndefined()
    expect(doc.takeChanges()).toEqual({ upserts: [expect.objectContaining({ id: mine.id, x: 70 })], deletes: [] })
  })

  it('ao recarregar troca tudo, mas guarda as pendências daqui', () => {
    const mine = newElement('note', 0, 0, 1)
    const old = newElement('rect', 300, 0, 2)
    const doc = useBoardDocument([mine, old])
    doc.update(mine.id, { props: { text: 'ainda não salvo' } })

    const restored = newElement('ellipse', 0, 0, 1)
    doc.reset([restored, { ...mine, props: { text: 'versão antiga' } }])

    expect([...doc.elements.keys()].sort()).toEqual([mine.id, restored.id].sort())
    expect(doc.get(mine.id)?.props.text).toBe('ainda não salvo')
    expect(doc.hasChanges()).toBe(true)
  })
})
