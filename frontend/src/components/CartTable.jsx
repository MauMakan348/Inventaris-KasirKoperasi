import { formatRupiah } from '../utils/format'

export default function CartTable({ lines, stocks, highlightedIds, onQuantityChange, onRemove }) {
  const highlighted = new Set(highlightedIds || [])

  if (lines.length === 0) {
    return <p className="cart-empty">Keranjang kosong.</p>
  }

  return (
    <div className="table-wrap">
      <table className="data-table cart-table">
        <thead>
          <tr>
            <th scope="col">Barang</th>
            <th scope="col">Harga</th>
            <th scope="col">Jumlah</th>
            <th scope="col">Subtotal</th>
            <th scope="col">
              <span className="sr-only">Aksi</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {lines.map((line) => {
            const stock = stocks?.[line.itemId]
            const overStock = typeof stock === 'number' && line.quantity > stock

            return (
              <tr
                key={line.itemId}
                className={highlighted.has(line.itemId) ? 'cart-row cart-row-highlight' : 'cart-row'}
              >
                <td>
                  <span className="cart-item-name">{line.name}</span>
                  <span className="cart-item-sku">{line.sku}</span>
                  {overStock && <p className="line-warning">Melebihi stok tampil ({stock})</p>}
                </td>
                <td>{formatRupiah(line.price)}</td>
                <td>
                  <input
                    className="qty-input"
                    type="number"
                    min="1"
                    max="1000"
                    step="1"
                    value={line.quantity}
                    aria-label={`Jumlah ${line.name}`}
                    onChange={(event) => onQuantityChange(line.itemId, event.target.value)}
                  />
                </td>
                <td>{formatRupiah(line.price * line.quantity)}</td>
                <td>
                  <button
                    className="btn btn-ghost btn-small"
                    type="button"
                    onClick={() => onRemove(line.itemId)}
                  >
                    Hapus
                  </button>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
