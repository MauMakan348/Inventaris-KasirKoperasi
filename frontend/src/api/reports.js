import client from './client'

export async function salesReport(from, to) {
  const response = await client.get('/reports/sales', {
    params: { from, to },
  })
  return response.data.data
}
