export default function StockShortageAlert({ items = [] }) {
  if (!Array.isArray(items) || items.length === 0) {
    return null
  }

  return (
    <div className="shortage">
      <p className="shortage-title">Barang dengan stok kurang:</p>
      <ul className="shortage-list">
        {items.map((item) => (
          <li key={item.item_id}>
            <strong>{item.name}</strong> diminta {item.requested}, tersedia {item.available}
          </li>
        ))}
      </ul>
    </div>
  )
}
