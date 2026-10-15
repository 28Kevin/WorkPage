<script setup>
import { onMounted, ref } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import api, { parseApiError } from '@/services/api'
import { formatDateTime } from '@/services/dates'

const email = ref('')
const sending = ref(false)
const downloading = ref(false)
const result = ref(null)
const warning = ref(null)
const error = ref(null)

/** Estado del correo saliente y últimos intentos. */
const state = ref(null)
const loading = ref(true)

onMounted(loadState)

async function loadState() {
  loading.value = true

  try {
    const { data } = await api.get('/backups')
    state.value = data
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
}

/**
 * Descarga directa: no pasa por el servidor de correo ni tiene el límite de
 * tamaño de un adjunto. El endpoint exige el token, así que se pide como blob.
 */
async function download() {
  downloading.value = true
  result.value = null
  warning.value = null
  error.value = null

  try {
    const { data, headers } = await api.get('/backups/download', { responseType: 'blob' })

    const name = filenameFrom(headers) || 'respaldo.zip'
    const url = URL.createObjectURL(new Blob([data], { type: 'application/zip' }))
    const link = document.createElement('a')

    link.href = url
    link.download = name
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)

    result.value = `Respaldo descargado: ${name}`
  } catch (err) {
    // El error llega como blob: hay que leerlo para ver el mensaje.
    error.value = await blobMessage(err)
  } finally {
    downloading.value = false
    await loadState()
  }
}

function filenameFrom(headers) {
  const match = /filename="?([^";]+)"?/i.exec(headers?.['content-disposition'] ?? '')

  return match ? match[1].trim() : null
}

async function blobMessage(err) {
  const data = err.response?.data

  if (data instanceof Blob) {
    try {
      return JSON.parse(await data.text()).message
    } catch {
      // Respuesta que no es JSON: se usa el mensaje genérico.
    }
  }

  return parseApiError(err).message
}

async function send() {
  sending.value = true
  result.value = null
  warning.value = null
  error.value = null

  try {
    const { data } = await api.post('/backups', { email: email.value || null })

    const detail = `Archivo: ${data.filename} (${Math.round(data.bytes / 1024)} KB).`

    // El mensaje solo se muestra como éxito si el correo salió de verdad.
    if (data.delivered) {
      result.value = `${data.message} ${detail}`
    } else {
      warning.value = `${data.message} ${detail} ${data.warning ?? ''}`.trim()
    }
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    sending.value = false
    await loadState()
  }
}

function size(bytes) {
  return bytes ? `${Math.round(bytes / 1024)} KB` : '—'
}
</script>

<template>
  <section class="card">
    <div class="section-heading">
      <span class="section-badge">8</span>
      <h2 class="text-sm font-semibold text-slate-900">Respaldo de la base de datos</h2>
    </div>

    <div class="space-y-4 p-5">
      <p class="text-sm leading-relaxed text-slate-600">
        Genera una copia completa de la base: exámenes, certificados, cuentas de cobro, configuración y
        galería. Guárdela fuera del servidor: sirve para restaurar aunque se pierda el acceso al proveedor.
      </p>

      <div>
        <button type="button" class="btn-primary" :disabled="downloading" @click="download">
          {{ downloading ? 'Generando…' : 'Descargar respaldo' }}
        </button>
        <p class="field-hint">El archivo se descarga a su equipo. No pasa por el servidor de correo.</p>
      </div>

      <AlertMessage v-if="result" variant="success">{{ result }}</AlertMessage>
      <AlertMessage v-if="warning" variant="error">{{ warning }}</AlertMessage>
      <AlertMessage v-if="error" variant="error">{{ error }}</AlertMessage>

      <!-- Envío por correo: secundario, para dejar la copia fuera del servidor. -->
      <details class="border-t border-slate-200 pt-4">
        <summary class="cursor-pointer text-sm font-medium text-slate-700">
          Enviar también por correo
        </summary>

        <AlertMessage v-if="state && !state.mail.deliverable" variant="error" class="mt-3">
          <strong>El correo saliente no está configurado.</strong>
          {{ state.mail.reason }}
        </AlertMessage>

        <div class="mt-3 flex flex-wrap items-end gap-3">
        <FormField
          v-slot="{ id }"
          label="Enviar a"
          class="min-w-64 flex-1"
          :hint="state?.configured_recipient
            ? `Si lo deja vacío se envía a ${state.configured_recipient}.`
            : 'Indique un correo de destino.'"
        >
          <input :id="id" v-model="email" type="email" class="field-input" placeholder="correo@ejemplo.com">
        </FormField>

        <button type="button" class="btn-secondary" :disabled="sending" @click="send">
          {{ sending ? 'Generando y enviando…' : 'Enviar por correo' }}
        </button>
        </div>
      </details>

      <!-- Historial: hace visible un respaldo que lleva tiempo fallando. -->
      <div class="border-t border-slate-200 pt-4">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Últimos intentos</h3>

          <p class="text-xs" :class="state?.last_successful_at ? 'text-slate-500' : 'text-red-700'">
            <template v-if="state?.last_successful_at">
              Último respaldo obtenido: {{ formatDateTime(state.last_successful_at) }}
            </template>
            <template v-else>Todavía no se ha obtenido ningún respaldo.</template>
          </p>
        </div>

        <p v-if="loading" class="text-sm text-slate-500">Cargando…</p>

        <p v-else-if="!state?.runs.length" class="text-sm text-slate-500">
          No se ha generado ningún respaldo.
        </p>

        <ul v-else class="divide-y divide-slate-100 text-sm">
          <li v-for="run in state.runs" :key="run.id" class="flex flex-wrap items-start gap-x-3 gap-y-1 py-2">
            <span
              class="rounded px-1.5 py-0.5 text-[10px] font-bold"
              :class="run.status === 'ok' && run.delivered
                ? 'bg-emerald-100 text-emerald-800'
                : 'bg-red-100 text-red-800'"
            >
              {{ run.status === 'ok' && run.delivered ? 'OK' : 'FALLÓ' }}
            </span>

            <span class="text-slate-600">{{ formatDateTime(run.created_at) }}</span>
            <span class="text-slate-500">
              {{ run.channel === 'download' ? 'Descarga' : run.recipient }}
            </span>
            <span class="text-slate-500">{{ size(run.bytes) }}</span>

            <span v-if="run.error" class="w-full text-xs text-red-700">{{ run.error }}</span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
