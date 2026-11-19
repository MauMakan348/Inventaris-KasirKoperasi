import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router'
import { parseApiError } from '../api/errors'
import { listItems } from '../api/items'
import { checkout as submitOrder } from '../api/orders'
import CartTable from '../components/CartTable'
import ErrorAlert from '../components/ErrorAlert'
import ItemPicker from '../components/ItemPicker'
import StockShortageAlert from '../components/StockShortageAlert'
import { useCart } from '../hooks/useCart'
import { useSubmitGuard } from '../hooks/useSubmitGuard'
import { buildCheckoutPayload } from '../utils/checkout'
import { formatRupiah } from '../utils/format'

const NETWORK_CHECKOUT_MESSAGE =
  'Koneksi terputus. Periksa halaman Pesanan Saya sebelum mengulang agar tidak tercatat dua kali.'

function OrderSuccess({ order, onShopAgain }) {
  const totalQuantity = (order.items || []).reduce((sum, item) => sum + item.quantity, 0)

  return (
    <section className="panel checkout-success" aria-live="polite">
      <h2>Pesanan berhasil</h2>
      <dl className="summary-list">
        <div>
          <dt>Kode</dt>
          <dd>{order.order_code}</dd>
        </div>
        <div>
          <dt>Total</dt>
          <dd>{formatRupiah(order.total_amount)}</dd>
        </div>
        <div>
          <dt>Jumlah barang</dt>
          <dd>{totalQuantity}</dd>
        </div>
      </dl>
      <div className="button-row">
        <Link className="btn btn-primary" to={`/orders/${order.id}`}>
          Lihat Struk
        </Link>
        <Link className="btn btn-ghost" to="/orders">
          Pesanan Saya
        </Link>
        <button className="btn btn-ghost" type="button" onClick={onShopAgain}>
          Belanja Lagi
        </button>
      </div>
    </section>
  )
}

export default function CatalogPage() {
  const { lines, lineCount, estimatedTotal, add, setQuantity, remove, clear } = useCart()
  const { submitting, run } = useSubmitGuard()

  const [items, setItems] = useState([])
  const [itemsLoading, setItemsLoading] = useState(true)
  const [itemsError, setItemsError] = useState(null)
  const [checkoutError, setCheckoutError] = useState(null)
  const [success, setSuccess] = useState(null)
  const [cartMessage, setCartMessage] = useState('')

  const loadItems = useCallback(async () => {
    try {
      setItems(await listItems())
      setItemsError(null)
    } catch (error) {
      setItemsError(parseApiError(error))
    } finally {
      setItemsLoading(false)
    }
  }, [])

  const reloadItems = useCallback(() => {
    setItemsLoading(true)
    setItemsError(null)
    loadItems()
  }, [loadItems])

  useEffect(() => {
    let active = true

    listItems()
      .then((data) => {
        if (active) {
          setItems(data)
          setItemsError(null)
        }
      })
      .catch((error) => {
        if (active) {
          setItemsError(parseApiError(error))
        }
      })
      .finally(() => {
        if (active) {
          setItemsLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  const stocks = useMemo(
    () => Object.fromEntries(items.map((item) => [item.id, item.stock])),
    [items],
  )

  const highlightedIds = useMemo(() => {
    if (!checkoutError) {
      return []
    }

    if (checkoutError.code === 'INSUFFICIENT_STOCK' && Array.isArray(checkoutError.details)) {
      return checkoutError.details.map((item) => item.item_id)
    }

    if (checkoutError.code === 'NOT_FOUND' && Array.isArray(checkoutError.details?.item_ids)) {
      return checkoutError.details.item_ids
    }

    return []
  }, [checkoutError])

  function clearFeedback() {
    setCheckoutError(null)
    setSuccess(null)
  }

  function handleAdd(item) {
    clearFeedback()
    setCartMessage('')

    if (!add(item)) {
      setCartMessage('Maksimal 50 baris keranjang.')
    }
  }

  function handleQuantityChange(itemId, quantity) {
    clearFeedback()
    setQuantity(itemId, quantity)
  }

  function handleRemove(itemId) {
    clearFeedback()
    remove(itemId)
  }

  function handleCheckout() {
    run(async () => {
      setCheckoutError(null)
      setCartMessage('')

      try {
        const order = await submitOrder(buildCheckoutPayload(lines, 'TRANSFER', ''))

        setSuccess(order)
        clear()
        await loadItems()
      } catch (error) {
        const parsed = parseApiError(error)
        setCheckoutError(parsed)

        if (parsed.status === 409 || parsed.status === 404) {
          await loadItems()
        }
      }
    })
  }

  const errorMessage = checkoutError?.code === 'NETWORK_ERROR' ? NETWORK_CHECKOUT_MESSAGE : undefined
  const canCheckout = !submitting && lines.length > 0

  return (
    <div className="pos-layout">
      <ItemPicker
        items={items}
        loading={itemsLoading}
        error={itemsError}
        onReload={reloadItems}
        onAdd={handleAdd}
      />

      {success ? (
        <OrderSuccess order={success} onShopAgain={() => setSuccess(null)} />
      ) : (
        <section className="panel">
          <div className="panel-head">
            <h2>Keranjang</h2>
            <span className="muted-text">{lineCount} baris</span>
          </div>

          <p className="alert-note">
            Pesanan langsung tercatat dan stok berkurang. Pembayaran dicatat sebagai transfer.
          </p>

          {cartMessage && (
            <p className="alert-warning" role="status">
              {cartMessage}
            </p>
          )}

          <CartTable
            lines={lines}
            stocks={stocks}
            highlightedIds={highlightedIds}
            onQuantityChange={handleQuantityChange}
            onRemove={handleRemove}
          />

          <p className="estimated-total">
            Total (perkiraan): <strong>{formatRupiah(estimatedTotal)}</strong>
          </p>

          {checkoutError && (
            <ErrorAlert error={checkoutError} message={errorMessage}>
              {checkoutError.code === 'INSUFFICIENT_STOCK' && (
                <StockShortageAlert items={checkoutError.details} />
              )}
              {checkoutError.code === 'NOT_FOUND' && Array.isArray(checkoutError.details?.item_ids) && (
                <p>Barang tidak ditemukan: {checkoutError.details.item_ids.join(', ')}</p>
              )}
            </ErrorAlert>
          )}

          <button
            className="btn btn-primary checkout-button"
            type="button"
            disabled={!canCheckout}
            onClick={handleCheckout}
          >
            {submitting ? 'Memproses pesanan...' : 'Pesan Sekarang'}
          </button>
        </section>
      )}
    </div>
  )
}
