export default function Loading({ label = 'Memuat...', fullScreen = false }) {
  return (
    <div className={fullScreen ? 'loading loading-fullscreen' : 'loading'} role="status" aria-live="polite">
      <span className="spinner" aria-hidden="true" />
      <span>{label}</span>
    </div>
  )
}
