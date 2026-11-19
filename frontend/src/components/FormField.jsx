export default function FormField({
  id,
  label,
  type = 'text',
  value,
  onChange,
  error,
  help,
  autoComplete,
  required = false,
}) {
  const errorId = `${id}-error`
  const helpId = `${id}-help`
  const describedBy = error ? errorId : help ? helpId : undefined

  return (
    <div className="form-field">
      <label htmlFor={id}>{label}</label>
      <input
        id={id}
        name={id}
        type={type}
        value={value}
        onChange={onChange}
        autoComplete={autoComplete}
        required={required}
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={describedBy}
      />
      {help && !error && (
        <p className="field-help" id={helpId}>
          {help}
        </p>
      )}
      {error && (
        <p className="field-error" id={errorId} role="alert">
          {error}
        </p>
      )}
    </div>
  )
}
