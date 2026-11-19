export const CHANNEL_LABELS = {
  POS: 'Kasir',
  ONLINE: 'Online',
}

export const PAYMENT_METHOD_LABELS = {
  CASH: 'Tunai',
  TRANSFER: 'Transfer',
}

export const MOVEMENT_TYPE_LABELS = {
  SALE_POS: 'Penjualan Kasir',
  SALE_ONLINE: 'Penjualan Online',
  RESTOCK: 'Restock',
  OPNAME: 'Stock Opname',
}

export function labelFor(labels, value) {
  return labels[value] || value || '-'
}
