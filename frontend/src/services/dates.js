/**
 * El servidor guarda en UTC y manda el desfase dentro del ISO, así que el
 * navegador lo convierte a la hora local de quien consulta.
 */
export function formatDateTime(iso) {
  if (!iso) return '—'

  return new Date(iso).toLocaleString('es-CO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
