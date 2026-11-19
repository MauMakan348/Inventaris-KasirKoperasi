import client from './client'

export async function checkout(payload) {
  const response = await client.post('/orders', payload)
  return response.data.data
}

export async function listOrders() {
  const response = await client.get('/orders')
  return response.data.data
}

export async function getOrder(id) {
  const response = await client.get(`/orders/${id}`)
  return response.data.data
}
