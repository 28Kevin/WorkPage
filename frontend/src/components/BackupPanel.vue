<script setup>
import { ref } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import api, { parseApiError } from '@/services/api'

const email = ref('')
const sending = ref(false)
const result = ref(null)
const error = ref(null)

async function send() {
  sending.value = true
  result.value = null
  error.value = null

  try {
    const { data } = await api.post('/backups', { email: email.value || null })

    result.value = `${data.message} Archivo: ${data.filename} (${Math.round(data.bytes / 1024)} KB).`
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <section class="card">
    <div class="section-heading">
      <span class="section-badge">4</span>
      <h2 class="text-sm font-semibold text-slate-900">Respaldo de la base de datos</h2>
    </div>

    <div class="space-y-4 p-5">
      <p class="text-sm leading-relaxed text-slate-600">
        Genera una copia completa de la base —exámenes, configuración y galería— y la envía como archivo
        adjunto. Guárdela fuera del servidor: sirve para restaurar aunque se pierda el acceso al proveedor.
      </p>

      <AlertMessage v-if="result" variant="success">{{ result }}</AlertMessage>
      <AlertMessage v-if="error" variant="error">{{ error }}</AlertMessage>

      <div class="flex flex-wrap items-end gap-3">
        <FormField
          class="min-w-64 flex-1"
          label="Enviar a"
          hint="Si lo deja vacío se usa el correo configurado en el servidor."
        >
          <template #default="{ id }">
            <input
              :id="id"
              v-model="email"
              type="email"
              class="field-input"
              placeholder="correo@ejemplo.com"
              autocomplete="email"
            >
          </template>
        </FormField>

        <button type="button" class="btn-primary" :disabled="sending" @click="send">
          {{ sending ? 'Generando y enviando…' : 'Generar respaldo y enviarlo' }}
        </button>
      </div>

      <p class="text-xs text-slate-500">
        El proceso tarda unos segundos: vuelca la base, la comprime y la envía. No cierre la página mientras
        termina.
      </p>
    </div>
  </section>
</template>
