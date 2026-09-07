import api from './api'

/**
 * El endpoint del PDF exige el token Bearer, por lo que no puede abrirse con un
 * enlace directo: se descarga como blob y se entrega al navegador.
 */
export async function downloadExamPdf(examId, filename = null) {
  const { data, headers } = await api.get(`/exams/${examId}/pdf`, { responseType: 'blob' })

  const name = filename || filenameFromHeaders(headers) || `examen-medico-${examId}.pdf`
  const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
  const link = document.createElement('a')

  link.href = url
  link.download = name
  document.body.appendChild(link)
  link.click()
  link.remove()

  URL.revokeObjectURL(url)
}

/** Abre el PDF en una pestaña nueva para previsualizarlo. */
export async function openExamPdf(examId) {
  const { data } = await api.get(`/exams/${examId}/pdf`, {
    params: { inline: 1 },
    responseType: 'blob',
  })

  const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))

  window.open(url, '_blank', 'noopener')

  // El navegador necesita la URL viva mientras carga la pestaña.
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}

/**
 * La cabecera trae dos variantes: `filename` en ASCII (sin tildes) y
 * `filename*` en UTF-8. Se prefiere la segunda para no perder los acentos del
 * nombre del trabajador.
 */
function filenameFromHeaders(headers) {
  const disposition = headers?.['content-disposition']

  if (!disposition) return null

  const utf8 = /filename\*=\s*UTF-8''([^;]+)/i.exec(disposition)

  if (utf8) {
    try {
      return decodeURIComponent(utf8[1].trim())
    } catch {
      // Secuencia porcentual invalida: se sigue con la variante ASCII.
    }
  }

  const ascii = /filename=\s*"?([^";]+)"?/i.exec(disposition)

  return ascii ? ascii[1].trim() : null
}
