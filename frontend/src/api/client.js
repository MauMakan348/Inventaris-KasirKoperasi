import axios from 'axios'

export const TOKEN_KEY = 'koperasi_token'

export function getToken() {
  return sessionStorage.getItem(TOKEN_KEY)
}

export function storeToken(token) {
  sessionStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  sessionStorage.removeItem(TOKEN_KEY)
}

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
  },
})

client.interceptors.request.use((config) => {
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const url = error.config?.url || ''
    const isLoginRequest = url.includes('/auth/login')

    // A failed login must keep its message on the form, so it never redirects.
    if (status === 401 && !isLoginRequest) {
      clearToken()

      if (window.location.pathname !== '/login') {
        const next = window.location.pathname + window.location.search
        window.location.replace(`/login?next=${encodeURIComponent(next)}`)
      }
    }

    return Promise.reject(error)
  },
)

export default client
