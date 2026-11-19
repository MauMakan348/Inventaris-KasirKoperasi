import { useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router'
import { parseApiError } from '../api/errors'
import FormField from '../components/FormField'
import { useAuth } from '../context/useAuth'
import { homePathForRole } from '../utils/roles'

function safeRedirect(candidate) {
  return typeof candidate === 'string' && candidate.startsWith('/') && !candidate.startsWith('//')
    ? candidate
    : null
}

export default function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()

  const [form, setForm] = useState({ email: '', password: '' })
  const [fieldErrors, setFieldErrors] = useState({})
  const [message, setMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)

  function handleChange(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setMessage('')
    setFieldErrors({})

    try {
      const user = await login(form.email.trim(), form.password)

      const fromLocation = location.state?.from
      const fromState = fromLocation ? `${fromLocation.pathname}${fromLocation.search || ''}` : null
      const target = safeRedirect(searchParams.get('next')) || safeRedirect(fromState)

      navigate(target || homePathForRole(user.role), { replace: true })
    } catch (error) {
      const parsed = parseApiError(error)
      setMessage(parsed.message)
      setFieldErrors(parsed.fieldErrors)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <p className="auth-eyebrow">Smart Koperasi</p>
        <h1>Masuk</h1>
        <p className="auth-subtitle">Gunakan akun koperasi Anda untuk melanjutkan.</p>

        {message && (
          <p className="alert-error" role="alert">
            {message}
          </p>
        )}

        <form className="auth-form" onSubmit={handleSubmit}>
          <FormField
            id="email"
            label="Email"
            type="email"
            value={form.email}
            onChange={handleChange}
            error={fieldErrors.email}
            autoComplete="email"
            required
          />
          <FormField
            id="password"
            label="Password"
            type="password"
            value={form.password}
            onChange={handleChange}
            error={fieldErrors.password}
            autoComplete="current-password"
            required
          />

          <button className="btn btn-primary" type="submit" disabled={submitting}>
            {submitting ? 'Memproses...' : 'Masuk'}
          </button>
        </form>

        <p className="auth-footer">
          Belum punya akun? <Link to="/register">Daftar sebagai anggota</Link>
        </p>
      </div>
    </div>
  )
}
