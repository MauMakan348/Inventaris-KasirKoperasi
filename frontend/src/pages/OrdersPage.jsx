import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { parseApiError } from '../api/errors'
import { listOrders } from '../api/orders'
import ErrorAlert from '../components/ErrorAlert'
import Loading from '../components/Loading'
import { useAuth } from '../context/useAuth'
import { formatDateTime, formatRupiah } from '../utils/format'

export default function OrdersPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [orders, setOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const loadOrders = useCallback(async () => {
    try {
      setOrders(await listOrders())
      setError(null)
    } catch (loadError) {
      setError(parseApiError(loadError))
    } finally {
      setLoading(false)
    }
  }, [])

  function retryLoad() {
    setLoading(true)
    setError(null)
    loadOrders()
  }

  useEffect(() => {
    let active = true

    listOrders()
      .then((data) => {
        if (active) {
          setOrders(data)
          setError(null)
        }
      })
      .catch((loadError) => {
        if (active) {
          setError(parseApiError(loadError))
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  const isMember = user.role === 'MEMBER'
  const title = isMember ? 'Pesanan Saya' : 'Riwayat Transaksi'

  return (
    <section className="panel">
      <div className="panel-head">
        <h2>{title}</h2>
      </div>

      {loading && <Loading label="Memuat transaksi..." />}

      {error && (
        <ErrorAlert error={error}>
          <button className="btn btn-ghost btn-small" type="button" onClick={retryLoad}>
            Coba lagi
          </button>
        </ErrorAlert>
      )}

      {!loading && !error && orders.length === 0 && (
        <p className="muted-text">Belum ada transaksi</p>
      )}

      {!loading && !error && orders.length > 0 && (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th scope="col">Kode</th>
                <th scope="col">Waktu</th>
                <th scope="col">Channel</th>
                <th scope="col">Pembayaran</th>
                <th scope="col">Total</th>
                {!isMember && <th scope="col">Pelaku</th>}
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr
                  key={order.id}
                  className="clickable-row"
                  onClick={() => navigate(`/orders/${order.id}`)}
                >
                  <td>
                    <Link to={`/orders/${order.id}`} onClick={(event) => event.stopPropagation()}>
                      {order.order_code}
                    </Link>
                  </td>
                  <td>{formatDateTime(order.created_at)}</td>
                  <td>{order.channel}</td>
                  <td>{order.payment_method}</td>
                  <td>{formatRupiah(order.total_amount)}</td>
                  {!isMember && <td>{order.user?.name}</td>}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
