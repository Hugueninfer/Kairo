import { onBeforeUnmount, reactive, ref } from 'vue'
import { realtime } from '@/services/realtime'
import type { BoardElement } from '@/types/api'

export interface BoardMember {
  id: number
  name: string
  can_edit: boolean
}

export interface RemoteCursor {
  id: number
  name: string
  color: string
  x: number
  y: number
  seenAt: number
}

/** Aviso da API: alguém salvou elementos (ou restaurou uma versão, quando reload = true). */
export interface BoardChangedEvent {
  editor: { id: number; name: string }
  reload: boolean
  upserts: BoardElement[]
  deletes: string[]
}

export type RealtimeStatus = 'off' | 'connecting' | 'online' | 'offline'

const MEMBER_COLORS = ['#E11D48', '#2563EB', '#16A34A', '#9333EA', '#EA580C', '#0891B2', '#CA8A04', '#DB2777']
const CURSOR_EVERY_MS = 50
const CURSOR_STALE_MS = 10_000

export function memberColor(id: number): string {
  return MEMBER_COLORS[Math.abs(id) % MEMBER_COLORS.length]!
}

interface CursorMessage {
  id: number
  x?: number
  y?: number
  gone?: boolean
}

interface PusherConnection {
  bind(event: 'state_change', callback: (states: { previous: string; current: string }) => void): void
  unbind(event: 'state_change', callback: (states: { previous: string; current: string }) => void): void
}

/**
 * Colaboração ao vivo num board: quem está vendo, cursores e alterações dos outros.
 *
 * As alterações chegam pela API (depois de salvas); os cursores vão direto entre os navegadores
 * (whisper), sem passar pelo banco.
 */
export function useBoardRealtime(
  boardId: string,
  me: { id: number },
  handlers: { onChanged: (event: BoardChangedEvent) => void; onResync: () => void; onVersionSaved?: () => void },
) {
  const members = ref<BoardMember[]>([])
  const cursors = reactive(new Map<number, RemoteCursor>())
  const status = ref<RealtimeStatus>('off')
  const name = `board.${boardId}`

  const client = realtime()
  const connection = (client?.connector.pusher.connection ?? null) as PusherConnection | null
  let lostConnection = false

  const channel = client
    ?.join(name)
    .here((list: BoardMember[]) => {
      members.value = list
      status.value = 'online'
    })
    .joining((member: BoardMember) => {
      members.value = [...members.value.filter((item) => item.id !== member.id), member]
    })
    .leaving((member: BoardMember) => {
      members.value = members.value.filter((item) => item.id !== member.id)
      cursors.delete(member.id)
    })
    .error(() => {
      status.value = 'offline'
    })
    .listen('.elements.changed', (event: BoardChangedEvent) => handlers.onChanged(event))
    .listen('.version.saved', () => handlers.onVersionSaved?.())
    .listenForWhisper('cursor', (message: CursorMessage) => {
      if (message.id === me.id) return
      const member = members.value.find((item) => item.id === message.id)
      if (!member || message.gone || message.x === undefined || message.y === undefined) {
        cursors.delete(message.id)
        return
      }
      cursors.set(message.id, { id: member.id, name: member.name, color: memberColor(member.id), x: message.x, y: message.y, seenAt: Date.now() })
    })

  if (client) status.value = 'connecting'

  /** Caiu e voltou: o que mudou nesse meio tempo não chegou, então recarrega o board. */
  function onStateChange({ current }: { previous: string; current: string }) {
    if (current === 'connected') {
      if (lostConnection) handlers.onResync()
      lostConnection = false
    } else if (current === 'unavailable' || current === 'disconnected' || current === 'failed') {
      lostConnection = true
      status.value = 'offline'
      cursors.clear()
    }
  }
  connection?.bind('state_change', onStateChange)

  let lastSent = 0
  let pending: ReturnType<typeof setTimeout> | undefined
  let latest: { x: number; y: number } | null = null

  /** Manda a posição do cursor (em coordenadas do quadro), no máximo a cada 50 ms. */
  function moveCursor(point: { x: number; y: number } | null) {
    if (!channel) return
    latest = point
    const send = () => {
      lastSent = Date.now()
      pending = undefined
      channel.whisper('cursor', latest ? { id: me.id, x: Math.round(latest.x), y: Math.round(latest.y) } : { id: me.id, gone: true })
    }
    const wait = CURSOR_EVERY_MS - (Date.now() - lastSent)
    if (wait <= 0) send()
    else pending ??= setTimeout(send, wait)
  }

  // Cursor parado há muito tempo (aba fechada sem aviso) some da tela.
  const sweep = setInterval(() => {
    const limit = Date.now() - CURSOR_STALE_MS
    for (const [id, cursor] of cursors) if (cursor.seenAt < limit) cursors.delete(id)
  }, CURSOR_STALE_MS / 2)

  function stop() {
    clearInterval(sweep)
    if (pending) clearTimeout(pending)
    connection?.unbind('state_change', onStateChange)
    client?.leave(name)
  }

  onBeforeUnmount(stop)

  return { members, cursors, status, moveCursor, stop }
}
