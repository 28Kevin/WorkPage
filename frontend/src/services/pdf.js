import api from './api'

/**
 * El endpoint del PDF exige el token Bearer, por lo que no puede abrirse con un
 * enlace directo: se descarga como blob y se entrega al navegador.
 */
async function downloadPdf(endpoint, fallbackName, filename = null) {
  const { data, headers } = await api.get(endpoint, { responseType: 'blob' })

  const name = filename || filenameFromHeaders(headers) || fallbackName
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
async function openPdf(endpoint) {
  const { data } = await api.get(endpoint, {
    params: { inline: 1 },
    responseType: 'blob',
  })

  const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))

  window.open(url, '_blank', 'noopener')

  // El navegador necesita la URL viva mientras carga la pestaña.
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}

// --- Examen medico ocupacional ---------------------------------------------

export function downloadExamPdf(examId, filename = null) {
  return downloadPdf(`/exams/${examId}/pdf`, `examen-medico-${examId}.pdf`, filename)
}

export function openExamPdf(examId) {
  return openPdf(`/exams/${examId}/pdf`)
}

// --- Paquete de manipulacion de alimentos ----------------------------------

export function downloadFoodHandlerPdf(recordId, filename = null) {
  return downloadPdf(`/food-handler/${recordId}/pdf`, `manipulacion-alimentos-${recordId}.pdf`, filename)
}

export function openFoodHandlerPdf(recordId) {
  return openPdf(`/food-handler/${recordId}/pdf`)
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

// --- Certificado de capacitacion -------------------------------------------

export function downloadCertificatePdf(certificateId, filename = null) {
  return downloadPdf(`/certificates/${certificateId}/pdf`, `certificado-${certificateId}.pdf`, filename)
}

export function openCertificatePdf(certificateId) {
  return openPdf(`/certificates/${certificateId}/pdf`)
}

// --- Cuenta de cobro --------------------------------------------------------

export function downloadBillingPdf(accountId, filename = null) {
  return downloadPdf(`/billing/${accountId}/pdf`, `cuenta-de-cobro-${accountId}.pdf`, filename)
}

export function openBillingPdf(accountId) {
  return openPdf(`/billing/${accountId}/pdf`)
}
