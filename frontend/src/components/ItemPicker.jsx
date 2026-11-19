import { useMemo, useState } from 'react'
import { formatRupiah } from '../utils/format'
import ErrorAlert from './ErrorAlert'
import Loading from './Loading'

export default function ItemPicker({ items, loading, error, onReload, onAdd }) {
  const [search, setSearch] = useState('')

  const filteredItems = useMemo(() => {
    const term = search.trim().toLowerCase()

    if (term === '') {
      return items
    }

    return items.filter(
      (item) =>
        item.name.toLowerCase().includes(term) || item.sku.toLowerCase().includes(term),
    )
  }, [items, search])

  return (
    <section className="panel">
      <div className="panel-head">
        <h2>Daftar Barang</h2>
        <button className="btn btn-ghost btn-small" type="button" onClick={onReload} disabled={loading}>
          {loading ? 'Memuat...' : 'Muat ulang stok'}
        </button>
      </div>

      <label className="sr-only" htmlFor="item-search">
        Cari barang
      </label>
      <input
        id="item-search"
        className="search-input"
        type="search"
        placeholder="Cari nama atau SKU"
        value={search}
        onChange={(event) => setSearch(event.target.value)}
      />

      {loading && <Loading label="Memuat barang..." />}
      {error && (
        <ErrorAlert error={error}>
          <button className="btn btn-ghost btn-small" type="button" onClick={onReload}>
            Coba lagi
          </button>
        </ErrorAlert>
      )}

      {!loading && !error && filteredItems.length === 0 && (
        <p className="muted-text">Tidak ada barang yang cocok.</p>
      )}

      <ul className="item-list">
        {filteredItems.map((item) => (
          <li className="item-card" key={item.id}>
            <div>
              <p className="item-name">{item.name}</p>
              <p className="item-meta">
                {item.sku} &middot; {formatRupiah(item.price)} &middot;{' '}
                {item.stock > 0 ? `Stok: ${item.stock}` : 'Habis'}
              </p>
            </div>
            <button
              className="btn btn-primary btn-small"
              type="button"
              disabled={item.stock <= 0}
              onClick={() => onAdd(item)}
            >
              Tambah
            </button>
          </li>
        ))}
      </ul>
    </section>
  )
}
