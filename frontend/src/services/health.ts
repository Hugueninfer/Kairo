import { http } from './http'

export interface HealthResponse {
  status: string
  app: string
  version: string
}

export async function fetchHealth(): Promise<HealthResponse> {
  const { data } = await http.get<HealthResponse>('/api/v1/health')
  return data
}
