import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router'
import { parseApiError } from '../api/errors'
import { listItems } from '../api/items'
import { listMovements, opname, restock } from '../api/stock'
import ErrorAlert from '../components/ErrorAlert'
import FormField from '../components/FormField'
import Loading from '../components/Loading'
import { useSubmitGuard } from '../hooks/useSubmitGuard'
import { formatDateTime } from '../utils/format'
import { labelFor, MOVEMENT_TYPE_LABELS } from '../utils/labels'

function toInteger(value) {
  return value === '' ? undefined : Number(value)
}

export default function AdminStockPage() {
  const [items, setItems] = useState([])
  const [itemsLoading, setItemsLoading] = useState(true)
  const [itemsError, setItemsError] = useState(null)
  const [selectedId, setSelectedId] = useState(null)

  const [movements, setMovements] = useState([])
  const [movementsLoading, setMovementsLoading] = useState(true)
  const [movementsError, setMovementsError] = useState(null)

  const [restockForm, setRestockForm] = useState({ quantity: '', note: '' })
  const [restockError, setRestockError] = useState(null)
  const [restockFieldErrors, setRestockFieldErrors] = useState({})
  const [restockSuccess, setRestockSuccess] = useState('')

  const [opnameForm, setOpnameForm] = useState({ actual_stock: '', note: '' })
  const [opnameError, setOpnameError] = useState(null)
  const [opnameFieldErrors, setOpnameFieldErrors] = useState({})
  const [opnameSuccess, setOpnameSuccess] = useState('')

  const restockGuard = useSubmitGuard()
  const opnameGuard = useSubmitGuard()

  const loadItems = useCallback(async () => {
    try {
      const data = await listItems()
      setItems(data)
      setItemsError(null)
      setSelectedId((current) => current ?? data[0]?.id ?? null)
    } catch (loadError) {
      setItemsError(parseApiError(loadError))
    } finally {
      setItemsLoading(false)
    }
  }, [])

  function retryLoadItems() {
    setItemsLoading(true)
    setItemsError(null)
    loadItems()
  }

  useEffect(() => {
    let active = true

    listItems()
      .then((data) => {
        if (active) {
          setItems(data)
          setItemsError(null)
          setSelectedId((current) => current ?? data[0]?.id ?? null)
        }
      })
      .catch((loadError) => {
        if (active) {
          setItemsError(parseApiError(loadError))
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

  useEffect(() => {
    if (!selectedId) {
      return undefined
    }

    let active = true

    listMovements(selectedId)
      .then((data) => {
        if (active) {
          setMovements(data)
          setMovementsError(null)
        }
      })
      .catch((loadError) => {
        if (active) {
          setMovementsError(parseApiError(loadError))
        }
      })
      .finally(() => {
        if (active) {
          setMovementsLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [selectedId])

  const loadMovements = useCallback(async (itemId) => {
    try {
      setMovements(await listMovements(itemId))
      setMovementsError(null)
    } catch (loadError) {
      setMovementsError(parseApiError(loadError))
    } finally {
      setMovementsLoading(false)
    }
  }, [])

  const selectedItem = items.find((item) => item.id === selectedId) || null

  function clearOperationMessages() {
    setRestockSuccess('')
    setOpnameSuccess('')
    setRestockError(null)
    setOpnameError(null)
    setRestockFieldErrors({})
    setOpnameFieldErrors({})
  }

  function handleSelectedChange(event) {
    const nextId = Number(event.target.value) || null
    setSelectedId(nextId)
    setMovements([])
    setMovementsLoading(Boolean(nextId))
    setMovementsError(null)
    clearOperationMessages()
  }

  function applyItemUpdate(updatedItem) {
    setItems((current) =>
      current.map((item) => (item.id === updatedItem.id ? updatedItem : item)),
    )
  }

  function handleRestock(event) {
    event.preventDefault()

    if (!selectedId) {
      return
    }

    restockGuard.run(async () => {
      setRestockError(null)
      setRestockFieldErrors({})
      setRestockSuccess('')

      try {
        const result = await restock(selectedId, {
          quantity: toInteger(restockForm.quantity),
          note: restockForm.note === '' ? undefined : restockForm.note,
        })

        applyItemUpdate(result.item)
        setRestockForm({ quantity: '', note: '' })
        setRestockSuccess('Restock berhasil.')
        await loadMovements(selectedId)
      } catch (saveError) {
        const parsed = parseApiError(saveError)

        if (parsed.status === 422 && Object.keys(parsed.fieldErrors).length > 0) {
          setRestockFieldErrors(parsed.fieldErrors)
        } else {
          setRestockError(parsed)
        }
      }
    })
  }

  function handleOpname(event) {
    event.preventDefault()

    if (!selectedId || !selectedItem) {
      return
    }

    if (opnameForm.actual_stock === '') {
      setOpnameFieldErrors({ actual_stock: 'Stok fisik wajib diisi.' })
      return
    }

    const actualStock = Number(opnameForm.actual_stock)
    const difference = actualStock - selectedItem.stock

    const confirmed = window.confirm(
      [
        `Stok sistem saat ini: ${selectedItem.stock}`,
        `Stok fisik yang diinput: ${actualStock}`,
        `Selisih: ${difference}`,
        '',
        'Lanjutkan stock opname?',
      ].join('\n'),
    )

    if (!confirmed) {
      return
    }

    opnameGuard.run(async () => {
      setOpnameError(null)
      setOpnameFieldErrors({})
      setOpnameSuccess('')

      try {
        const result = await opname(selectedId, {
          actual_stock: actualStock,
          note: opnameForm.note === '' ? undefined : opnameForm.note,
        })

        applyItemUpdate(result.item)
        setOpnameForm({ actual_stock: '', note: '' })
        setOpnameSuccess(
          result.movement === null
            ? 'Stok sudah sesuai, tidak ada perubahan.'
            : 'Stock opname berhasil.',
        )
        await loadMovements(selectedId)
      } catch (saveError) {
        const parsed = parseApiError(saveError)

        if (parsed.status === 422 && Object.keys(parsed.fieldErrors).length > 0) {
          setOpnameFieldErrors(parsed.fieldErrors)
        } else {
          setOpnameError(parsed)
        }
      }
    })
  }

  if (itemsLoading) {
    return <Loading label="Memuat barang..." />
  }

  if (itemsError) {
    return (
      <section className="panel">
        <ErrorAlert error={itemsError}>
          <button className="btn btn-ghost btn-small" type="button" onClick={retryLoadItems}>
            Coba lagi
          </button>
        </ErrorAlert>
      </section>
    )
  }

  if (items.length === 0) {
    return (
      <section className="panel">
        <h2>Stok</h2>
        <p className="muted-text">Belum ada barang. Tambahkan barang terlebih dahulu.</p>
      </section>
    )
  }

  return (
    <div className="stack">
      <section className="panel">
        <div className="panel-head">
          <h2>Stok Barang</h2>
          {selectedItem && <span className="muted-text">Stok saat ini: {selectedItem.stock}</span>}
        </div>

        <div className="form-field">
          <label htmlFor="stock-item">Pilih barang</label>
          <select id="stock-item" value={selectedId ?? ''} onChange={handleSelectedChange}>
            {items.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name} ({item.sku})
              </option>
            ))}
          </select>
        </div>
      </section>

      <div className="admin-columns">
        <section className="panel">
          <h2>Restock</h2>

          <form className="admin-form" onSubmit={handleRestock}>
            {restockError && <ErrorAlert error={restockError} />}
            {restockSuccess && (
              <p className="alert-success" role="status">
                {restockSuccess}
              </p>
            )}

            <FormField
              id="restock-quantity"
              label="Jumlah masuk"
              type="number"
              value={restockForm.quantity}
              onChange={(event) =>
                setRestockForm((current) => ({ ...current, quantity: event.target.value }))
              }
              error={restockFieldErrors.quantity}
            />
            <FormField
              id="restock-note"
              label="Catatan (opsional)"
              value={restockForm.note}
              onChange={(event) =>
                setRestockForm((current) => ({ ...current, note: event.target.value }))
              }
              error={restockFieldErrors.note}
            />

            <button className="btn btn-primary" type="submit" disabled={restockGuard.submitting}>
              {restockGuard.submitting ? 'Menyimpan...' : 'Simpan Restock'}
            </button>
          </form>
        </section>

        <section className="panel">
          <h2>Stock Opname</h2>

          <form className="admin-form" onSubmit={handleOpname}>
            {opnameError && <ErrorAlert error={opnameError} />}
            {opnameSuccess && (
              <p className="alert-success" role="status">
                {opnameSuccess}
              </p>
            )}

            <FormField
              id="opname-actual"
              label="Stok fisik"
              type="number"
              value={opnameForm.actual_stock}
              onChange={(event) =>
                setOpnameForm((current) => ({ ...current, actual_stock: event.target.value }))
              }
              error={opnameFieldErrors.actual_stock}
            />
            <FormField
              id="opname-note"
              label="Catatan (opsional)"
              value={opnameForm.note}
              onChange={(event) =>
                setOpnameForm((current) => ({ ...current, note: event.target.value }))
              }
              error={opnameFieldErrors.note}
            />

            <button className="btn btn-primary" type="submit" disabled={opnameGuard.submitting}>
              {opnameGuard.submitting ? 'Menyimpan...' : 'Simpan Opname'}
            </button>
          </form>
        </section>
      </div>

      <section className="panel">
        <div className="panel-head">
          <h2>Riwayat Mutasi</h2>
          {movementsLoading && <span className="muted-text">Memuat...</span>}
        </div>

        {movementsError && (
          <ErrorAlert error={movementsError}>
            <button
              className="btn btn-ghost btn-small"
              type="button"
              onClick={() => {
                setMovementsLoading(true)
                setMovementsError(null)
                loadMovements(selectedId)
              }}
            >
              Coba lagi
            </button>
          </ErrorAlert>
        )}

        {!movementsLoading && !movementsError && movements.length === 0 && (
          <p className="muted-text">Belum ada mutasi stok.</p>
        )}

        {!movementsLoading && !movementsError && movements.length > 0 && (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th scope="col">Waktu</th>
                  <th scope="col">Tipe</th>
                  <th scope="col">Perubahan</th>
                  <th scope="col">Stok sebelum</th>
                  <th scope="col">Stok sesudah</th>
                  <th scope="col">Pelaku</th>
                  <th scope="col">Catatan</th>
                </tr>
              </thead>
              <tbody>
                {movements.map((movement) => (
                  <tr key={movement.id}>
                    <td>{formatDateTime(movement.created_at)}</td>
                    <td>
                      {movement.order_id ? (
                        <Link to={`/orders/${movement.order_id}`}>
                          {labelFor(MOVEMENT_TYPE_LABELS, movement.type)}
                        </Link>
                      ) : (
                        labelFor(MOVEMENT_TYPE_LABELS, movement.type)
                      )}
                    </td>
                    <td
                      className={
                        movement.quantity_change > 0 ? 'movement-positive' : 'movement-negative'
                      }
                    >
                      {movement.quantity_change > 0
                        ? `+${movement.quantity_change}`
                        : movement.quantity_change}
                    </td>
                    <td>{movement.stock_before}</td>
                    <td>{movement.stock_after}</td>
                    <td>{movement.user?.name || '-'}</td>
                    <td>{movement.note || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  )
}
