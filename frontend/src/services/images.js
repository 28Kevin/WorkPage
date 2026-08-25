/**
 * Las imágenes viajan dentro del JSON como data URI, así que conviene
 * reducirlas en el navegador antes de mandarlas al servidor.
 */

export const MAX_UPLOAD_BYTES = 5 * 1024 * 1024

/** Lee un archivo del disco como data URI. */
export function readAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()

    reader.onload = () => resolve(reader.result)
    reader.onerror = () => reject(new Error('read'))
    reader.readAsDataURL(file)
  })
}

/** Reduce la imagen manteniendo su proporción y la devuelve como data URI. */
export function downscale(dataUrl, maxSize, { format = 'image/jpeg', quality = 0.82 } = {}) {
  return new Promise((resolve, reject) => {
    const image = new Image()

    image.onload = () => {
      const scale = Math.min(1, maxSize / Math.max(image.width, image.height))
      const canvas = document.createElement('canvas')

      canvas.width = Math.round(image.width * scale)
      canvas.height = Math.round(image.height * scale)

      const context = canvas.getContext('2d')

      // El JPEG no tiene transparencia: sin fondo blanco saldría en negro.
      if (format === 'image/jpeg') {
        context.fillStyle = '#ffffff'
        context.fillRect(0, 0, canvas.width, canvas.height)
      }

      context.drawImage(image, 0, 0, canvas.width, canvas.height)

      resolve(canvas.toDataURL(format, quality))
    }

    image.onerror = () => reject(new Error('decode'))
    image.src = dataUrl
  })
}

/**
 * Toma el archivo de un <input type="file"> y devuelve el data URI ya reducido.
 * Los SVG se dejan intactos: no pasan por canvas.
 *
 * @returns {Promise<string>}
 */
export async function prepareImage(file, maxSize, options = {}) {
  if (file.size > MAX_UPLOAD_BYTES) {
    throw new Error('too-large')
  }

  const dataUrl = await readAsDataUrl(file)

  return file.type === 'image/svg+xml' ? dataUrl : downscale(dataUrl, maxSize, options)
}
