import client from './client'

export async function listItems() {
  const response = await client.get('/items')
  return response.data.data
}

export async function createItem(payload) {
  const response = await client.post('/items', payload)
  return response.data.data
}

export async function updateItem(id, payload) {
  const response = await client.put(`/items/${id}`, payload)
  return response.data.data
}
