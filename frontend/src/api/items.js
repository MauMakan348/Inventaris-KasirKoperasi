import client from './client'

export async function listItems() {
  const response = await client.get('/items')
  return response.data.data
}
