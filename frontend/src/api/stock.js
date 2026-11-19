import client from './client'

export async function restock(itemId, payload) {
  const response = await client.post(`/items/${itemId}/restock`, payload)
  return response.data.data
}

export async function opname(itemId, payload) {
  const response = await client.post(`/items/${itemId}/stock-opname`, payload)
  return response.data.data
}

export async function listMovements(itemId) {
  const response = await client.get(`/items/${itemId}/movements`)
  return response.data.data
}
