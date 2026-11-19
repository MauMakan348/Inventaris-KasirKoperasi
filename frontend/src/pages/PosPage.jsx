import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router'
import { parseApiError } from '../api/errors'
import { listItems } from '../api/items'
import { checkout as submitOrder } from '../api/orders'
import CartTable from '../components/CartTable'
import ErrorAlert from '../components/ErrorAlert'
import ItemPicker from '../components/ItemPicker'
import StockShortageAlert from '../components/StockShortageAlert'
import { useCart } from '../hooks/useCart'
import { buildCheckoutPayload } from '../utils/checkout'
import { formatRupiah } from '../utils/format'

const NETWORK_CHECKOUT_MESSAGE =
  'Koneksi terputus. Periksa halaman Transaksi sebelum mengulang agar tidak tercatat dua kali.'

function CheckoutResult({ order, onNewTransaction }) {
  const totalQuantity = (order.items || []).reduce((sum, item) => sum + item.quantity, 0)

  return (
    <section className="panel checkout-success" aria-live="polite">
      <h2>Transaksi berhasil</h2>
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
          <dt>Dibayar</dt>
          <dd>{formatRupiah(order.amount_paid)}</dd>
        </div>
        <div>
          <dt>Kembalian</dt>
          <dd>{formatRupiah(order.change_amount)}</dd>
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
        <button className="btn btn-ghost" type="button" onClick={onNewTransaction}>
          Transaksi Baru
        </button>
      </div>
    </section>
  )
}

export default function PosPage() {
  const { lines, lineCount, estimatedTotal, add, setQuantity, remove, clear } = useCart()

  const [items, setItems] = useState([])
  const [itemsLoading, setItemsLoading] = useState(true)
  const [itemsError, setItemsError] = useState(null)

  const [paymentMethod, setPaymentMethod] = useState('CASH')
  const [amountPaid, setAmountPaid] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [checkoutError, setCheckoutError] = useState(null)
  const [success, setSuccess] = useState(null)
  const [cartMessage, setCartMessage] = useState('')

  const submittingRef = useRef(false)

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

  const paidAmount = Number(amountPaid)
  const hasAmountPaid = amountPaid !== ''
  const isCashShort =
    paymentMethod === 'CASH' && hasAmountPaid && Number.isFinite(paidAmount) && paidAmount < estimatedTotal

  const canCheckout =
    !submitting && lines.length > 0 && (paymentMethod !== 'CASH' || hasAmountPaid)

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

  function handlePaymentMethodChange(method) {
    setPaymentMethod(method)
    clearFeedback()

    if (method === 'TRANSFER') {
      setAmountPaid('')
    }
  }

  function handleAmountPaidChange(event) {
    setAmountPaid(event.target.value)
    setCheckoutError(null)
  }

  function handleCheckout() {
    if (submittingRef.current) {
      return
    }

    submittingRef.current = true
    setSubmitting(true)
    setCheckoutError(null)
    setCartMessage('')

    async function sendCheckout() {
      try {
        const order = await submitOrder(buildCheckoutPayload(lines, paymentMethod, amountPaid))

        setSuccess(order)
        clear()
        setPaymentMethod('CASH')
        setAmountPaid('')
        await loadItems()
      } catch (error) {
        const parsed = parseApiError(error)
        setCheckoutError(parsed)

        if (parsed.status === 409 || parsed.status === 404) {
          await loadItems()
        }
      } finally {
        setSubmitting(false)
        submittingRef.current = false
      }
    }

    sendCheckout()
  }

  const errorMessage = checkoutError?.code === 'NETWORK_ERROR' ? NETWORK_CHECKOUT_MESSAGE : undefined

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
        <CheckoutResult order={success} onNewTransaction={() => setSuccess(null)} />
      ) : (
        <section className="panel">
          <div className="panel-head">
            <h2>Keranjang</h2>
            <span className="muted-text">{lineCount} baris</span>
          </div>

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

          <fieldset className="payment-methods">
            <legend>Metode pembayaran</legend>
            <label>
              <input
                type="radio"
                name="payment_method"
                value="CASH"
                checked={paymentMethod === 'CASH'}
                onChange={() => handlePaymentMethodChange('CASH')}
              />
              Tunai (CASH)
            </label>
            <label>
              <input
                type="radio"
                name="payment_method"
                value="TRANSFER"
                checked={paymentMethod === 'TRANSFER'}
                onChange={() => handlePaymentMethodChange('TRANSFER')}
              />
              Transfer
            </label>
          </fieldset>

          {paymentMethod === 'CASH' && (
            <div className="form-field">
              <label htmlFor="amount-paid">Uang dibayar</label>
              <input
                id="amount-paid"
                type="number"
                min="0"
                step="1"
                value={amountPaid}
                onChange={handleAmountPaidChange}
              />
              {isCashShort && <p className="line-warning">Uang kurang dari perkiraan total</p>}
              {!isCashShort && hasAmountPaid && Number.isFinite(paidAmount) && (
                <p className="field-help">
                  Perkiraan kembalian: {formatRupiah(Math.max(0, paidAmount - estimatedTotal))}
                </p>
              )}
            </div>
          )}

          {checkoutError && (
            <ErrorAlert error={checkoutError} message={errorMessage}>
              {checkoutError.code === 'INSUFFICIENT_STOCK' && (
                <StockShortageAlert items={checkoutError.details} />
              )}
              {checkoutError.code === 'NOT_FOUND' && Array.isArray(checkoutError.details?.item_ids) && (
                <p>Barang tidak ditemukan: {checkoutError.details.item_ids.join(', ')}</p>
              )}
              {checkoutError.code === 'INSUFFICIENT_PAYMENT' && checkoutError.details && (
                <p>
                  Total tagihan {formatRupiah(checkoutError.details.total_amount)}, uang dibayar{' '}
                  {formatRupiah(checkoutError.details.amount_paid)}.
                </p>
              )}
            </ErrorAlert>
          )}

          <button
            className="btn btn-primary checkout-button"
            type="button"
            disabled={!canCheckout}
            onClick={handleCheckout}
          >
            {submitting ? 'Memproses checkout...' : 'Checkout'}
          </button>
        </section>
      )}
    </div>
  )
}
