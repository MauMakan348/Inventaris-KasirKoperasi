import { useState } from 'react'
import { Navigate, Route, Routes } from 'react-router'
import AppLayout from './components/AppLayout'
import Loading from './components/Loading'
import ProtectedRoute from './components/ProtectedRoute'
import { useAuth } from './context/useAuth'
import LoginPage from './pages/LoginPage'
import NotFoundPage from './pages/NotFoundPage'
import OrdersPage from './pages/OrdersPage'
import Placeholder from './pages/Placeholder'
import PosPage from './pages/PosPage'
import ReceiptPage from './pages/ReceiptPage'
import RegisterPage from './pages/RegisterPage'
import { homePathForRole } from './utils/roles'

function GuestRoute({ children }) {
  const { user } = useAuth()
  // Snapshot avoids racing the redirect that LoginPage performs after a successful login.
  const [initiallyAuthenticated] = useState(() => Boolean(user))

  if (initiallyAuthenticated) {
    return <Navigate to={homePathForRole(user.role)} replace />
  }

  return children
}

function HomeRedirect() {
  const { user } = useAuth()

  return <Navigate to={user ? homePathForRole(user.role) : '/login'} replace />
}

function App() {
  const { loading } = useAuth()

  if (loading) {
    return <Loading fullScreen label="Memeriksa sesi..." />
  }

  return (
    <Routes>
      <Route
        path="/login"
        element={
          <GuestRoute>
            <LoginPage />
          </GuestRoute>
        }
      />
      <Route
        path="/register"
        element={
          <GuestRoute>
            <RegisterPage />
          </GuestRoute>
        }
      />

      <Route path="/" element={<HomeRedirect />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route
            path="/pos"
            element={
              <ProtectedRoute roles={['CASHIER']}>
                <PosPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/catalog"
            element={
              <ProtectedRoute roles={['MEMBER']}>
                <Placeholder title="Katalog" description="Katalog anggota akan dibuat pada sesi 8C." />
              </ProtectedRoute>
            }
          />
          <Route path="/orders" element={<OrdersPage />} />
          <Route path="/orders/:id" element={<ReceiptPage />} />
          <Route
            path="/admin/items"
            element={
              <ProtectedRoute roles={['ADMIN']}>
                <Placeholder title="Barang" description="Pengelolaan barang akan dibuat pada sesi 8C." />
              </ProtectedRoute>
            }
          />
          <Route
            path="/admin/stock"
            element={
              <ProtectedRoute roles={['ADMIN']}>
                <Placeholder title="Stok" description="Restock dan opname akan dibuat pada sesi 8C." />
              </ProtectedRoute>
            }
          />
          <Route
            path="/admin/reports"
            element={
              <ProtectedRoute roles={['ADMIN']}>
                <Placeholder title="Laporan" description="Laporan penjualan akan dibuat pada sesi 8C." />
              </ProtectedRoute>
            }
          />
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}

export default App
