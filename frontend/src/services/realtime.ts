import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { http } from './http'

// Tempo real dos boards via Laravel Reverb (protocolo do Pusher).
// A autorização dos canais passa pelo mesmo cliente HTTP do resto do app: cookie de sessão + CSRF,
// sem token no navegador.

export type RealtimeClient = Echo<'reverb'>

/** Resposta da API ao autorizar um canal (assinatura no formato do Pusher). */
interface ChannelAuth {
  auth: string
  channel_data?: string
}

let client: RealtimeClient | null = null

/** Sem a chave do Reverb configurada o app funciona normalmente, só sem colaboração ao vivo. */
export function realtimeEnabled(): boolean {
  return Boolean(import.meta.env.VITE_REVERB_APP_KEY)
}

export function realtime(): RealtimeClient | null {
  if (!realtimeEnabled()) return null
  if (client) return client

  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'https'
  const port = Number(import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80))

  client = new Echo({
    broadcaster: 'reverb',
    Pusher,
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    // O header X-Socket-ID vai pelo interceptor abaixo, no nosso cliente HTTP.
    withoutInterceptors: true,
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: ChannelAuth | null) => void) => {
        http
          .post<ChannelAuth>('/api/v1/company/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then(({ data }) => callback(null, data))
          .catch((error: unknown) => callback(error instanceof Error ? error : new Error('Acesso negado ao canal.'), null))
      },
    }),
  })

  return client
}

/** Fecha a conexão (ao sair da conta, por exemplo). */
export function disconnectRealtime(): void {
  client?.disconnect()
  client = null
}

// Com o id da conexão no header, a API avisa os outros e não devolve o aviso para quem fez a alteração.
http.interceptors.request.use((config) => {
  const socketId = client?.socketId()
  if (socketId) config.headers.set('X-Socket-ID', socketId)
  return config
})
