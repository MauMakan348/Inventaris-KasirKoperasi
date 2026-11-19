import { useEffect, useState } from 'react'
import * as authApi from '../api/auth'
import { clearToken, getToken, storeToken } from '../api/client'
import { AuthContext } from './auth-context'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [token, setToken] = useState(() => getToken())
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true

    async function restoreSession() {
      if (!getToken()) {
        setLoading(false)
        return
      }

      try {
        const currentUser = await authApi.me()

        if (active) {
          setUser(currentUser)
          setToken(getToken())
        }
      } catch {
        clearToken()

        if (active) {
          setUser(null)
          setToken(null)
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    restoreSession()

    return () => {
      active = false
    }
  }, [])

  function applySession(session) {
    storeToken(session.token)
    setToken(session.token)
    setUser(session.user)
    return session.user
  }

  async function login(email, password) {
    return applySession(await authApi.login({ email, password }))
  }

  async function register(payload) {
    const result = await authApi.register(payload)

    // The backend currently returns the created user without a token.
    if (result?.token && result?.user) {
      return applySession(result)
    }

    return applySession(await authApi.login({ email: payload.email, password: payload.password }))
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch {
      // The local session must end even when the revoke request fails.
    } finally {
      clearToken()
      setToken(null)
      setUser(null)
    }
  }

  return (
    <AuthContext.Provider value={{ user, token, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  )
}
