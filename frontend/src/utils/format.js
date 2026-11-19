const BUSINESS_TIMEZONE = import.meta.env.VITE_BUSINESS_TIMEZONE || 'Asia/Makassar'

export function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value) || 0)
}

export function formatDateTime(isoString) {
  if (!isoString) {
    return '-'
  }

  const date = new Date(isoString)

  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  const formatted = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: BUSINESS_TIMEZONE,
  }).format(date)

  return BUSINESS_TIMEZONE === 'Asia/Makassar' ? `${formatted} WITA` : formatted
}
