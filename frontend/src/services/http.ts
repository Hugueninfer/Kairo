import axios from 'axios'

// Cliente HTTP único da aplicação. O front nunca fala com o banco: tudo passa pela API.
// withCredentials + withXSRFToken fazem o Sanctum (modo SPA) usar cookie HttpOnly e proteção CSRF,
// sem guardar token no localStorage.
export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export async function ensureCsrfCookie(): Promise<void> {
  await http.get('/sanctum/csrf-cookie')
}
