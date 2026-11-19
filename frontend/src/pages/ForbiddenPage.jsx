import { Link } from 'react-router'
import { useAuth } from '../context/useAuth'
import { homePathForRole } from '../utils/roles'

export default function ForbiddenPage() {
  const { user } = useAuth()

  return (
    <section className="page-card state-page">
      <p className="state-code">403</p>
      <h1>Anda tidak memiliki akses</h1>
      <p>Halaman ini hanya tersedia untuk role lain. Hubungi admin bila Anda merasa ini keliru.</p>
      <Link className="btn btn-primary" to={user ? homePathForRole(user.role) : '/login'}>
        Kembali ke beranda
      </Link>
    </section>
  )
}
