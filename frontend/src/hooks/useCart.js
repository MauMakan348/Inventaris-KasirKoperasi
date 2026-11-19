import { useCallback, useMemo, useState } from 'react'

const MAX_LINES = 50
const MIN_QUANTITY = 1
const MAX_QUANTITY = 1000

function clampQuantity(value) {
  const quantity = Math.trunc(Number(value))

  if (!Number.isFinite(quantity)) {
    return MIN_QUANTITY
  }

  return Math.min(MAX_QUANTITY, Math.max(MIN_QUANTITY, quantity))
}

export function useCart() {
  const [lines, setLines] = useState([])

  const add = useCallback(
    (item) => {
      const alreadyInCart = lines.some((line) => line.itemId === item.id)

      if (!alreadyInCart && lines.length >= MAX_LINES) {
        return false
      }

      setLines((current) => {
        const existing = current.find((line) => line.itemId === item.id)

        if (existing) {
          return current.map((line) =>
            line.itemId === item.id
              ? { ...line, quantity: clampQuantity(line.quantity + 1) }
              : line,
          )
        }

        if (current.length >= MAX_LINES) {
          return current
        }

        return [
          ...current,
          {
            itemId: item.id,
            sku: item.sku,
            name: item.name,
            price: item.price,
            quantity: 1,
          },
        ]
      })

      return true
    },
    [lines],
  )

  const setQuantity = useCallback((itemId, quantity) => {
    setLines((current) =>
      current.map((line) =>
        line.itemId === itemId ? { ...line, quantity: clampQuantity(quantity) } : line,
      ),
    )
  }, [])

  const remove = useCallback((itemId) => {
    setLines((current) => current.filter((line) => line.itemId !== itemId))
  }, [])

  const clear = useCallback(() => {
    setLines([])
  }, [])

  const estimatedTotal = useMemo(
    () => lines.reduce((total, line) => total + line.price * line.quantity, 0),
    [lines],
  )

  return {
    lines,
    lineCount: lines.length,
    estimatedTotal,
    add,
    setQuantity,
    remove,
    clear,
  }
}
