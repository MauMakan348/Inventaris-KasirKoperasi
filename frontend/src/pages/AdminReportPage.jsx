import { useState } from 'react'
import { parseApiError } from '../api/errors'
import { salesReport } from '../api/reports'
import ErrorAlert from '../components/ErrorAlert'
import FormField from '../components/FormField'
import Loading from '../components/Loading'
import { formatRupiah, todayDateString } from '../utils/format'
import { CHANNEL_LABELS, labelFor } from '../utils/labels'

const MILLISECONDS_PER_DAY = 24 * 60 * 60 * 1000

function dayDifference(from, to) {
  const [fromYear, fromMonth, fromDay] = from.split('-').map(Number)
  const [toYear, toMonth, toDay] = to.split('-').map(Number)

  const start = Date.UTC(fromYear, fromMonth - 1, fromDay)
  const end = Date.UTC(toYear, toMonth - 1, toDay)

  return Math.floor((end - start) / MILLISECONDS_PER_DAY)
}

function validatePeriod(from, to) {
  const errors = {}

  if (from === '') {
    errors.from = 'Tanggal awal wajib diisi.'
  }

  if (to === '') {
    errors.to = 'Tanggal akhir wajib diisi.'
  }

  if (errors.from || errors.to) {
    return errors
  }

  if (from > to) {
    errors.to = 'Tanggal akhir harus sama atau setelah tanggal awal.'
    return errors
  }

  if (dayDifference(from, to) > 366) {
    errors.to = 'Rentang maksimal 366 hari.'
  }

  return errors
}

export default function AdminReportPage() {
  const [from, setFrom] = useState(() => todayDateString())
  const [to, setTo] = useState(() => todayDateString())
  const [report, setReport] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [fieldErrors, setFieldErrors] = useState({})

  function handleSubmit(event) {
    event.preventDefault()

    const validationErrors = validatePeriod(from, to)

    if (Object.keys(validationErrors).length > 0) {
      setFieldErrors(validationErrors)
      return
    }

    setFieldErrors({})
    setError(null)
    setLoading(true)

    salesReport(from, to)
      .then((data) => {
        setReport(data)
      })
      .catch((loadError) => {
        const parsed = parseApiError(loadError)

        if (parsed.status === 422 && Object.keys(parsed.fieldErrors).length > 0) {
          setFieldErrors(parsed.fieldErrors)
        } else {
          setError(parsed)
        }
      })
      .finally(() => {
        setLoading(false)
      })
  }

  return (
    <div className="stack">
      <section className="panel">
        <div className="panel-head">
          <h2>Laporan Penjualan</h2>
        </div>

        <form className="report-filter" onSubmit={handleSubmit}>
          <FormField
            id="report-from"
            label="Dari tanggal"
            type="date"
            value={from}
            onChange={(event) => setFrom(event.target.value)}
            error={fieldErrors.from}
          />
          <FormField
            id="report-to"
            label="Sampai tanggal"
            type="date"
            value={to}
            onChange={(event) => setTo(event.target.value)}
            error={fieldErrors.to}
          />
          <button className="btn btn-primary" type="submit" disabled={loading}>
            {loading ? 'Memuat...' : 'Tampilkan'}
          </button>
        </form>
      </section>

      {error && (
        <section className="panel">
          <ErrorAlert error={error} />
        </section>
      )}

      {loading && (
        <section className="panel">
          <Loading label="Memuat laporan..." />
        </section>
      )}

      {!loading && report && (
        <>
          <section className="panel">
            <p className="muted-text">
              Periode {report.period.from} sampai {report.period.to} ({report.period.timezone})
            </p>

            <div className="summary-cards">
              <div className="summary-card">
                <span className="summary-label">Total transaksi</span>
                <strong>{report.total_orders}</strong>
              </div>
              <div className="summary-card">
                <span className="summary-label">Total pendapatan</span>
                <strong>{formatRupiah(report.total_revenue)}</strong>
              </div>
            </div>

            {report.total_orders === 0 && (
              <p className="muted-text">Belum ada penjualan pada periode ini.</p>
            )}
          </section>

          <section className="panel">
            <h2>Per Channel</h2>
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th scope="col">Channel</th>
                    <th scope="col">Total transaksi</th>
                    <th scope="col">Total pendapatan</th>
                  </tr>
                </thead>
                <tbody>
                  {report.by_channel.map((row) => (
                    <tr key={row.channel}>
                      <td>{labelFor(CHANNEL_LABELS, row.channel)}</td>
                      <td>{row.total_orders}</td>
                      <td>{formatRupiah(row.total_revenue)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="panel">
            <h2>Barang Terlaris</h2>

            {report.top_items.length === 0 ? (
              <p className="muted-text">Tidak ada data barang terjual.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th scope="col">Barang</th>
                      <th scope="col">Jumlah terjual</th>
                      <th scope="col">Pendapatan</th>
                    </tr>
                  </thead>
                  <tbody>
                    {report.top_items.map((item) => (
                      <tr key={item.item_id}>
                        <td>{item.item_name}</td>
                        <td>{item.quantity_sold}</td>
                        <td>{formatRupiah(item.revenue)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </div>
  )
}
