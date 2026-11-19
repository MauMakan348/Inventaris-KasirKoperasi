import { useCallback, useEffect, useState } from 'react'
import { parseApiError } from '../api/errors'
import { createItem, listItems, updateItem } from '../api/items'
import ErrorAlert from '../components/ErrorAlert'
import FormField from '../components/FormField'
import Loading from '../components/Loading'
import { useSubmitGuard } from '../hooks/useSubmitGuard'
import { formatRupiah } from '../utils/format'

const EMPTY_FORM = { sku: '', name: '', price: '', stock: '' }

function toInteger(value) {
  return value === '' ? undefined : Number(value)
}

export default function AdminItemsPage() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [successMessage, setSuccessMessage] = useState('')

  const [formMode, setFormMode] = useState(null)
  const [editingId, setEditingId] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formError, setFormError] = useState(null)
  const [fieldErrors, setFieldErrors] = useState({})

  const { submitting, run } = useSubmitGuard()

  const loadItems = useCallback(async () => {
    try {
      setItems(await listItems())
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
    loadItems()
  }

  useEffect(() => {
    let active = true

    listItems()
      .then((data) => {
        if (active) {
          setItems(data)
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

  function openCreateForm() {
    setFormMode('create')
    setEditingId(null)
    setForm(EMPTY_FORM)
    setFormError(null)
    setFieldErrors({})
    setSuccessMessage('')
  }

  function openEditForm(item) {
    setFormMode('edit')
    setEditingId(item.id)
    setForm({
      sku: item.sku,
      name: item.name,
      price: String(item.price),
      stock: String(item.stock),
    })
    setFormError(null)
    setFieldErrors({})
    setSuccessMessage('')
  }

  function closeForm() {
    setFormMode(null)
    setEditingId(null)
    setFormError(null)
    setFieldErrors({})
  }

  function setField(name, value) {
    setForm((current) => ({ ...current, [name]: value }))
  }

  function handleSubmit(event) {
    event.preventDefault()

    run(async () => {
      setFormError(null)
      setFieldErrors({})
      setSuccessMessage('')

      try {
        if (formMode === 'create') {
          await createItem({
            sku: form.sku,
            name: form.name,
            price: toInteger(form.price),
            stock: toInteger(form.stock),
          })
        } else {
          await updateItem(editingId, {
            sku: form.sku,
            name: form.name,
            price: toInteger(form.price),
          })
        }

        const message = formMode === 'create' ? 'Barang berhasil ditambahkan.' : 'Barang berhasil diubah.'
        closeForm()
        setSuccessMessage(message)
        await loadItems()
      } catch (saveError) {
        const parsed = parseApiError(saveError)

        if (parsed.status === 422 && Object.keys(parsed.fieldErrors).length > 0) {
          setFieldErrors(parsed.fieldErrors)
        } else {
          setFormError(parsed)
        }
      }
    })
  }

  const isEditing = formMode === 'edit'

  return (
    <div className="stack">
      {formMode && (
        <section className="panel">
          <div className="panel-head">
            <h2>{isEditing ? 'Ubah Barang' : 'Tambah Barang'}</h2>
          </div>

          <form className="admin-form" onSubmit={handleSubmit}>
            {formError && <ErrorAlert error={formError} />}

            <div className="form-grid">
              <FormField
                id="item-sku"
                label="SKU"
                value={form.sku}
                onChange={(event) => setField('sku', event.target.value)}
                error={fieldErrors.sku}
              />
              <FormField
                id="item-name"
                label="Nama"
                value={form.name}
                onChange={(event) => setField('name', event.target.value)}
                error={fieldErrors.name}
              />
              <FormField
                id="item-price"
                label="Harga"
                type="number"
                value={form.price}
                onChange={(event) => setField('price', event.target.value)}
                error={fieldErrors.price}
              />

              {isEditing ? (
                <div className="form-field">
                  <label htmlFor="item-stock">Stok</label>
                  <input id="item-stock" type="text" value={form.stock} readOnly />
                  <p className="field-help">Ubah stok lewat menu Stok.</p>
                </div>
              ) : (
                <FormField
                  id="item-stock"
                  label="Stok awal"
                  type="number"
                  value={form.stock}
                  onChange={(event) => setField('stock', event.target.value)}
                  error={fieldErrors.stock}
                />
              )}
            </div>

            <div className="button-row">
              <button className="btn btn-primary" type="submit" disabled={submitting}>
                {submitting ? 'Menyimpan...' : 'Simpan'}
              </button>
              <button className="btn btn-ghost" type="button" onClick={closeForm} disabled={submitting}>
                Batal
              </button>
            </div>
          </form>
        </section>
      )}

      <section className="panel">
        <div className="panel-head">
          <h2>Daftar Barang</h2>
          <button className="btn btn-primary btn-small" type="button" onClick={openCreateForm}>
            Tambah Barang
          </button>
        </div>

        {successMessage && (
          <p className="alert-success" role="status">
            {successMessage}
          </p>
        )}

        {loading && <Loading label="Memuat barang..." />}

        {error && (
          <ErrorAlert error={error}>
            <button className="btn btn-ghost btn-small" type="button" onClick={retryLoad}>
              Coba lagi
            </button>
          </ErrorAlert>
        )}

        {!loading && !error && items.length === 0 && <p className="muted-text">Belum ada barang.</p>}

        {!loading && !error && items.length > 0 && (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th scope="col">SKU</th>
                  <th scope="col">Nama</th>
                  <th scope="col">Harga</th>
                  <th scope="col">Stok</th>
                  <th scope="col">
                    <span className="sr-only">Aksi</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id}>
                    <td>{item.sku}</td>
                    <td>{item.name}</td>
                    <td>{formatRupiah(item.price)}</td>
                    <td>{item.stock}</td>
                    <td>
                      <button
                        className="btn btn-ghost btn-small"
                        type="button"
                        onClick={() => openEditForm(item)}
                      >
                        Ubah
                      </button>
                    </td>
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
