import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useAuth } from '../context/useAuth'
import { homePathForRole, ROLE_LABELS } from '../utils/roles'

const NAV_BY_ROLE = {
  ADMIN: [
    { to: '/admin/items', label: 'Barang' },
    { to: '/admin/stock', label: 'Stok' },
    { to: '/admin/reports', label: 'Laporan' },
    { to: '/orders', label: 'Transaksi' },
  ],
  CASHIER: [
    { to: '/pos', label: 'Kasir' },
    { to: '/orders', label: 'Transaksi' },
  ],
  MEMBER: [
    { to: '/catalog', label: 'Katalog' },
    { to: '/orders', label: 'Pesanan Saya' },
  ],
}

export default function AppLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [loggingOut, setLoggingOut] = useState(false)

  const items = NAV_BY_ROLE[user.role] || []

  async function handleLogout() {
    setLoggingOut(true)
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="app-shell">
      <header className="app-header">
        <Link className="brand" to={homePathForRole(user.role)}>
          Smart Koperasi
        </Link>
        <div className="user-box">
          <span className="user-name">{user.name}</span>
          <span className={`role-badge role-${user.role.toLowerCase()}`}>
            {ROLE_LABELS[user.role] || user.role}
          </span>
          <button className="btn btn-ghost" type="button" onClick={handleLogout} disabled={loggingOut}>
            {loggingOut ? 'Keluar...' : 'Keluar'}
          </button>
        </div>
      </header>

      <nav className="app-nav" aria-label="Navigasi utama">
        {items.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) => (isActive ? 'nav-link nav-link-active' : 'nav-link')}
          >
            {item.label}
          </NavLink>
        ))}
      </nav>

      <main className="app-content">
        <Outlet />
      </main>
    </div>
  )
}
