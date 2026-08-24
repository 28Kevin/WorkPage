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

function filenameFromHeaders(headers) {
  const disposition = headers?.['content-disposition']

  if (!disposition) return null

  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)

  return match ? decodeURIComponent(match[1]) : null
}
