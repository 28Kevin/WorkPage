<script setup>
import { onMounted, reactive, ref } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import api, { parseApiError } from '@/services/api'

/**
 * Credenciales del servidor de correo.
 *
 * La contraseña nunca llega del servidor: el formulario solo sabe si hay una
 * guardada. Dejarla vacía al guardar conserva la que ya estaba.
 */
const form = reactive({
  transport: 'smtp',
  api_key: '',
  host: '',
  port: 587,
  username: '',
  password: '',
  encryption: 'tls',
  from_address: '',
  from_name: '',
})

const hasPassword = ref(false)
const hasApiKey = ref(false)
const status = ref(null)

const ready = ref(false)
const saving = ref(false)
const testing = ref(false)
const testEmail = ref('')
const notice = ref(null)
const error = ref(null)
const errors = ref({})

onMounted(load)

async function load() {
  try {
    const { data } = await api.get('/mail-settings')
    fill(data)
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    ready.value = true
  }
}

function fill(data) {
  const settings = data.settings

  Object.assign(form, {
    transport: settings.transport ?? 'smtp',
    api_key: '',
    host: settings.host ?? '',
    port: settings.port ?? 587,
    username: settings.username ?? '',
    password: '',
    encryption: settings.encryption ?? 'tls',
    from_address: settings.from_address ?? '',
    from_name: settings.from_name ?? '',
  })

  hasPassword.value = settings.has_password
  hasApiKey.value = settings.has_api_key
  status.value = data.status
}

async function save() {
  saving.value = true
  notice.value = null
  error.value = null
  errors.value = {}

  try {
    const { data } = await api.put('/mail-settings', {
      ...form,
      port: Number(form.port) || null,
      // Vacíos significan "deja los que hay", no "bórralos".
      password: form.password || null,
      api_key: form.api_key || null,
    })

    fill(data)
    notice.value = data.message
  } catch (err) {
    const parsed = parseApiError(err)
    errors.value = parsed.errors
    error.value = parsed.message
  } finally {
    saving.value = false
  }
}

async function forgetPassword() {
  try {
    await api.delete('/mail-settings/password')
    hasPassword.value = false
    hasApiKey.value = false
    notice.value = 'Credenciales eliminadas.'
  } catch (err) {
    error.value = parseApiError(err).message
  }
}

/** Único modo de saber si las credenciales sirven de verdad. */
async function sendTest() {
  testing.value = true
  notice.value = null
  error.value = null

  try {
    const { data } = await api.post('/mail-settings/test', { email: testEmail.value })
    notice.value = data.message
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    testing.value = false
  }
}
</script>

<template>
  <section class="card">
    <div class="section-heading">
      <span class="section-badge">7</span>
      <h2 class="text-sm font-semibold text-slate-900">Correo saliente</h2>
    </div>

    <div class="p-5">
      <p class="mb-4 text-sm leading-relaxed text-slate-600">
        Servidor por el que sale el correo de la plataforma, incluidos los respaldos. Lo que se guarde
        aquí reemplaza a las variables de entorno del servidor.
      </p>

      <AlertMessage v-if="status && !status.deliverable" variant="error" class="mb-4">
        {{ status.reason }}
      </AlertMessage>
      <AlertMessage v-else-if="status" variant="success" class="mb-4">
        El correo está configurado y puede enviarse.
      </AlertMessage>

      <AlertMessage v-if="notice" variant="success" class="mb-4">{{ notice }}</AlertMessage>
      <AlertMessage v-if="error" variant="error" class="mb-4">{{ error }}</AlertMessage>

      <p v-if="!ready" class="text-sm text-slate-500">Cargando…</p>

      <form v-else class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
        <FormField
          v-slot="{ id }"
          label="Vía de envío"
          class="sm:col-span-2"
          :error="errors.transport"
          hint="Si el servidor de hosting bloquea los puertos de SMTP, use Resend: viaja por HTTPS."
        >
          <select :id="id" v-model="form.transport" class="field-input">
            <option value="smtp">Servidor SMTP (Gmail, Outlook…)</option>
            <option value="resend">Resend (API por HTTPS)</option>
          </select>
        </FormField>

        <FormField
          v-if="form.transport === 'resend'"
          v-slot="{ id }"
          label="Clave de API"
          class="sm:col-span-2"
          :error="errors.api_key"
          :hint="hasApiKey ? 'Hay una clave guardada. Déjelo vacío para conservarla.' : 'Se obtiene en el panel de Resend.'"
        >
          <input
            :id="id"
            v-model="form.api_key"
            type="password"
            class="field-input"
            autocomplete="off"
            placeholder="re_…"
          >
        </FormField>

        <template v-if="form.transport === 'smtp'">
        <FormField v-slot="{ id }" label="Servidor (host)" :error="errors.host">
          <input :id="id" v-model="form.host" type="text" class="field-input" placeholder="smtp.gmail.com">
        </FormField>

        <FormField v-slot="{ id }" label="Puerto" :error="errors.port">
          <input :id="id" v-model="form.port" type="number" min="1" max="65535" class="field-input">
        </FormField>

        <FormField v-slot="{ id }" label="Usuario" :error="errors.username">
          <input :id="id" v-model="form.username" type="text" class="field-input" autocomplete="off">
        </FormField>

        <FormField
          v-slot="{ id }"
          label="Contraseña"
          :error="errors.password"
          :hint="hasPassword
            ? 'Hay una contraseña guardada. Déjelo vacío para conservarla.'
            : 'Sin contraseña guardada.'"
        >
          <input
            :id="id"
            v-model="form.password"
            type="password"
            class="field-input"
            autocomplete="new-password"
            placeholder="••••••••"
          >
        </FormField>

        <FormField v-slot="{ id }" label="Cifrado" :error="errors.encryption">
          <select :id="id" v-model="form.encryption" class="field-input">
            <option value="tls">TLS (puerto 587)</option>
            <option value="ssl">SSL (puerto 465)</option>
            <option value="">Ninguno</option>
          </select>
        </FormField>

        </template>

        <!--
          Resend exige que el dominio del remitente esté verificado: una
          dirección de Gmail o similar la rechaza.
        -->
        <FormField
          v-slot="{ id }"
          label="Correo remitente"
          :error="errors.from_address"
          :hint="form.transport === 'resend'
            ? 'Debe ser de un dominio verificado en Resend. Sin dominio propio, use onboarding@resend.dev.'
            : 'En Gmail y Outlook debe coincidir con el usuario.'"
        >
          <input
            :id="id"
            v-model="form.from_address"
            type="email"
            class="field-input"
            :placeholder="form.transport === 'resend' ? 'onboarding@resend.dev' : ''"
          >
        </FormField>

        <FormField v-slot="{ id }" label="Nombre del remitente" :error="errors.from_name">
          <input :id="id" v-model="form.from_name" type="text" class="field-input">
        </FormField>

        <div class="flex flex-wrap items-center justify-end gap-2 sm:col-span-2">
          <button
            v-if="hasPassword || hasApiKey"
            type="button"
            class="btn-ghost text-xs text-red-700"
            @click="forgetPassword"
          >
            Borrar credenciales guardadas
          </button>

          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Guardando…' : 'Guardar configuración' }}
          </button>
        </div>
      </form>

      <!-- Que los campos estén llenos no significa que el servidor acepte. -->
      <div class="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-4">
        <FormField
          v-slot="{ id }"
          label="Enviar un correo de prueba a"
          class="min-w-64 flex-1"
          hint="Es la única forma de confirmar que las credenciales sirven."
        >
          <input :id="id" v-model="testEmail" type="email" class="field-input" placeholder="correo@ejemplo.com">
        </FormField>

        <button
          type="button"
          class="btn-secondary"
          :disabled="testing || !testEmail"
          @click="sendTest"
        >
          {{ testing ? 'Enviando…' : 'Enviar prueba' }}
        </button>
      </div>
    </div>
  </section>
</template>
