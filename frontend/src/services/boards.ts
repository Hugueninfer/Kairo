import { http } from './http'
import type { ApiResource, Board, BoardElement, BoardVersion, Paginated, PublicBoard } from '@/types/api'

export interface BoardPayload {
  title: string
  project_id?: string | null
}

export interface BoardChanges {
  upserts: BoardElement[]
  deletes: string[]
}

const board = (id: string) => `/api/v1/company/boards/${encodeURIComponent(id)}`

export const boardsApi = {
  async list(params: { search?: string; project?: string; page?: number } = {}): Promise<Paginated<Board>> {
    const { data } = await http.get<Paginated<Board>>('/api/v1/company/boards', { params })
    return data
  },

  async get(id: string): Promise<Board> {
    const { data } = await http.get<ApiResource<Board>>(board(id))
    return data.data
  },

  async create(payload: BoardPayload): Promise<Board> {
    const { data } = await http.post<ApiResource<Board>>('/api/v1/company/boards', payload)
    return data.data
  },

  async update(id: string, payload: BoardPayload): Promise<Board> {
    const { data } = await http.put<ApiResource<Board>>(board(id), payload)
    return data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(board(id))
  },

  /** Autosave: grava o lote de elementos criados, alterados e apagados. */
  async sync(id: string, changes: BoardChanges): Promise<{ updated_at: string }> {
    const { data } = await http.post<ApiResource<{ updated_at: string }>>(`${board(id)}/elements`, changes)
    return data.data
  },

  async versions(id: string, page = 1): Promise<Paginated<BoardVersion>> {
    const { data } = await http.get<Paginated<BoardVersion>>(`${board(id)}/versions`, { params: { page } })
    return data
  },

  async version(id: string, versionId: string): Promise<BoardVersion> {
    const { data } = await http.get<ApiResource<BoardVersion>>(`${board(id)}/versions/${encodeURIComponent(versionId)}`)
    return data.data
  },

  /** A API guarda a versão na fila: ela aparece na lista em seguida. */
  async saveVersion(id: string, label: string | null): Promise<void> {
    await http.post(`${board(id)}/versions`, { label })
  },

  async restore(id: string, versionId: string): Promise<Board> {
    const { data } = await http.post<ApiResource<Board>>(`${board(id)}/versions/${encodeURIComponent(versionId)}/restore`)
    return data.data
  },

  async share(id: string): Promise<Board> {
    const { data } = await http.post<ApiResource<Board>>(`${board(id)}/share`)
    return data.data
  },

  async unshare(id: string): Promise<Board> {
    const { data } = await http.delete<ApiResource<Board>>(`${board(id)}/share`)
    return data.data
  },
}

/** Board compartilhado com o cliente: sem login, só leitura. */
export const publicBoardApi = {
  async get(token: string): Promise<PublicBoard> {
    const { data } = await http.get<ApiResource<PublicBoard>>(`/api/v1/public/boards/${encodeURIComponent(token)}`)
    return data.data
  },
}
