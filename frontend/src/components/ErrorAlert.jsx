export default function ErrorAlert({ error, message, children }) {
  if (!error) {
    return null
  }

  const fieldErrors = Object.entries(error.fieldErrors || {})

  return (
    <div className="alert-error" role="alert">
      <p className="alert-title">{message || error.message}</p>
      {fieldErrors.length > 0 && (
        <ul className="alert-list">
          {fieldErrors.map(([field, fieldMessage]) => (
            <li key={field}>{fieldMessage}</li>
          ))}
        </ul>
      )}
      {children}
    </div>
  )
}
