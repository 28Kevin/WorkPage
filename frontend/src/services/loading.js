import { ref } from 'vue'

/*
 * Peticiones en curso que muestran el loader global. Solo cuentan las que no
 * tienen indicador propio: escrituras (guardar, eliminar) y descargas de
 * archivos. Las lecturas ya muestran su spinner dentro de cada vista.
 * Una petición puede excluirse con { silent: true } en su configuración.
 */
export const pendingRequests = ref(0)

export function tracksLoading(config) {
  if (config.silent) return false

  const method = (config.method || 'get').toLowerCase()

  return method !== 'get' || config.responseType === 'blob'
}
