import { Link } from 'react-router'
import { useAuth } from '../context/useAuth'
import { homePathForRole } from '../utils/roles'

export default function NotFoundPage() {
  const { user } = useAuth()

  return (
    <div className="auth-page">
      <section className="page-card state-page">
        <p className="state-code">404</p>
        <h1>Halaman tidak ditemukan</h1>
        <p>Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.</p>
        <Link className="btn btn-primary" to={user ? homePathForRole(user.role) : '/login'}>
          {user ? 'Kembali ke beranda' : 'Masuk'}
        </Link>
      </section>
    </div>
  )
}
