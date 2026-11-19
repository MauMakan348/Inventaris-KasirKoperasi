import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { parseApiError } from '../api/errors'
import { getOrder } from '../api/orders'
import ErrorAlert from '../components/ErrorAlert'
import Loading from '../components/Loading'
import { formatDateTime, formatRupiah } from '../utils/format'
import { CHANNEL_LABELS, labelFor, PAYMENT_METHOD_LABELS } from '../utils/labels'

export default function ReceiptPage() {
  const { id } = useParams()
  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const loadOrder = useCallback(async () => {
    try {
      setOrder(await getOrder(id))
      setError(null)
    } catch (loadError) {
      setError(parseApiError(loadError))
    } finally {
      setLoading(false)
    }
  }, [id])

  function retryLoad() {
    setLoading(true)
    setError(null)
    loadOrder()
  }

  useEffect(() => {
    let active = true

    getOrder(id)
      .then((data) => {
        if (active) {
          setOrder(data)
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
  }, [id])

  if (loading) {
    return <Loading label="Memuat struk..." />
  }

  if (error?.status === 404) {
    return (
      <section className="page-card state-page">
        <h1>Transaksi tidak ditemukan</h1>
        <p>Transaksi ini tidak ada atau bukan milik Anda.</p>
        <Link className="btn btn-primary" to="/orders">
          Kembali ke daftar transaksi
        </Link>
      </section>
    )
  }

  if (error) {
    return (
      <section className="panel">
        <ErrorAlert error={error}>
          <button className="btn btn-ghost btn-small" type="button" onClick={retryLoad}>
            Coba lagi
          </button>
        </ErrorAlert>
        <Link to="/orders">Kembali ke daftar transaksi</Link>
      </section>
    )
  }

  const isTransfer = order.payment_method === 'TRANSFER'

  return (
    <div className="receipt-page">
      <div className="receipt-toolbar no-print">
        <button className="btn btn-primary" type="button" onClick={() => window.print()}>
          Cetak
        </button>
        <Link className="btn btn-ghost" to="/orders">
          Kembali
        </Link>
      </div>

      <article className="receipt">
        <header className="receipt-header">
          <h1>Smart Koperasi</h1>
          <p>Struk Transaksi</p>
        </header>

        <dl className="receipt-meta">
          <div>
            <dt>Kode</dt>
            <dd>{order.order_code}</dd>
          </div>
          <div>
            <dt>Waktu</dt>
            <dd>{formatDateTime(order.created_at)}</dd>
          </div>
          <div>
            <dt>Pelaku</dt>
            <dd>{order.user?.name}</dd>
          </div>
          <div>
            <dt>Channel</dt>
            <dd>{labelFor(CHANNEL_LABELS, order.channel)}</dd>
          </div>
          <div>
            <dt>Pembayaran</dt>
            <dd>{labelFor(PAYMENT_METHOD_LABELS, order.payment_method)}</dd>
          </div>
        </dl>

        <table className="receipt-table">
          <thead>
            <tr>
              <th scope="col">Barang</th>
              <th scope="col">Jumlah</th>
              <th scope="col">Harga</th>
              <th scope="col">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            {order.items.map((item) => (
              <tr key={item.id}>
                <td>{item.item_name}</td>
                <td>{item.quantity}</td>
                <td>{formatRupiah(item.unit_price)}</td>
                <td>{formatRupiah(item.subtotal)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <dl className="receipt-totals">
          <div>
            <dt>Total</dt>
            <dd>{formatRupiah(order.total_amount)}</dd>
          </div>
          <div>
            <dt>Dibayar</dt>
            <dd>{formatRupiah(order.amount_paid)}</dd>
          </div>
          {!isTransfer && (
            <div>
              <dt>Kembalian</dt>
              <dd>{formatRupiah(order.change_amount)}</dd>
            </div>
          )}
        </dl>

        <p className="receipt-footer">Terima kasih telah berbelanja.</p>
      </article>
    </div>
  )
}
