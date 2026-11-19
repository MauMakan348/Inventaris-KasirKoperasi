const FALLBACK_MESSAGE = 'Terjadi kesalahan. Silakan coba lagi.'

function readFieldErrors(status, details) {
  if (status !== 422 || details === null || typeof details !== 'object' || Array.isArray(details)) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(details).map(([field, messages]) => {
      const first = Array.isArray(messages) ? messages[0] : messages
      return [field, typeof first === 'string' ? first : String(first ?? '')]
    }),
  )
}

export function parseApiError(error) {
  if (!error.response) {
    return {
      status: null,
      code: 'NETWORK_ERROR',
      message: 'Tidak dapat terhubung ke server.',
      details: null,
      fieldErrors: {},
    }
  }

  const status = error.response.status
  const apiError = error.response.data?.error

  if (!apiError || typeof apiError !== 'object') {
    return {
      status,
      code: 'UNKNOWN_ERROR',
      message: FALLBACK_MESSAGE,
      details: null,
      fieldErrors: {},
    }
  }

  const details = apiError.details ?? null

  return {
    status,
    code: typeof apiError.code === 'string' ? apiError.code : 'UNKNOWN_ERROR',
    message: typeof apiError.message === 'string' ? apiError.message : FALLBACK_MESSAGE,
    details,
    fieldErrors: readFieldErrors(status, details),
  }
}
