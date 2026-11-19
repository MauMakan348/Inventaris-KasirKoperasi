import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { parseApiError } from '../api/errors'
import FormField from '../components/FormField'
import { useAuth } from '../context/useAuth'
import { homePathForRole } from '../utils/roles'

export default function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()

  const [form, setForm] = useState({ name: '', email: '', password: '' })
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
      const user = await register({
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
      })

      navigate(homePathForRole(user.role), { replace: true })
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
        <h1>Daftar Anggota</h1>
        <p className="auth-subtitle">Registrasi terbuka hanya untuk anggota koperasi.</p>

        {message && (
          <p className="alert-error" role="alert">
            {message}
          </p>
        )}

        <form className="auth-form" onSubmit={handleSubmit}>
          <FormField
            id="name"
            label="Nama lengkap"
            value={form.name}
            onChange={handleChange}
            error={fieldErrors.name}
            autoComplete="name"
            required
          />
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
            autoComplete="new-password"
            help="Minimal 8 karakter."
            required
          />

          <button className="btn btn-primary" type="submit" disabled={submitting}>
            {submitting ? 'Memproses...' : 'Daftar'}
          </button>
        </form>

        <p className="auth-footer">
          Sudah punya akun? <Link to="/login">Masuk</Link>
        </p>
      </div>
    </div>
  )
}
