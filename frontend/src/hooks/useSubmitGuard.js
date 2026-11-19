import { useCallback, useRef, useState } from 'react'

export function useSubmitGuard() {
  const [submitting, setSubmitting] = useState(false)
  const guardRef = useRef(false)

  const run = useCallback(async (task) => {
    if (guardRef.current) {
      return undefined
    }

    guardRef.current = true
    setSubmitting(true)

    try {
      return await task()
    } finally {
      guardRef.current = false
      setSubmitting(false)
    }
  }, [])

  return { submitting, run }
}
