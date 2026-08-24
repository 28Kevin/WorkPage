<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import api, { parseApiError } from '@/services/api'
import { useBrandingStore } from '@/stores/branding'

const branding = useBrandingStore()

const form = reactive({ name: '', email: '', phone: '', subject: '', message: '' })

const errors = ref({})
const sending = ref(false)
const sent = ref(null)
const failed = ref(null)

const schedule = computed(() =>
  (branding.center.schedule || '').split('\n').map((line) => line.trim()).filter(Boolean),
)

/** El enlace de WhatsApp necesita el número sin espacios ni símbolos. */
const whatsapp = computed(() => {
  const digits = (branding.center.phone || '').replace(/\D/g, '')

  return digits.length >= 10 ? `https://wa.me/${digits}` : null
})

onMounted(() => branding.load())

async function submit() {
  sending.value = true
  errors.value = {}
  failed.value = null
  sent.value = null

  try {
    const { data } = await api.post('/public/contact', {
      ...form,
      phone: form.phone || null,
    })

    sent.value = data.message
    Object.assign(form, { name: '', email: '', phone: '', subject: '', message: '' })
  } catch (error) {
    const parsed = parseApiError(error)

    errors.value = parsed.errors
    failed.value = error.response?.status === 429
      ? 'Ha enviado varios mensajes seguidos. Espere un minuto e intente de nuevo.'
      : parsed.message
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <header class="text-center">
      <p class="text-xs font-semibold tracking-[0.18em] text-brand-700 uppercase">Contáctenos</p>
      <h1 class="mt-2 text-3xl font-bold text-slate-900">Estamos para atenderle</h1>
      <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
        Puede agendar una cita, solicitar una cotización para su empresa o resolver una consulta sobre
        nuestros exámenes.
      </p>
    </header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
      <!-- ----------------------------------------------------- datos de contacto -->
      <aside class="space-y-3 lg:col-span-2">
        <div class="card p-5">
          <h2 class="text-sm font-bold text-slate-900">Información de contacto</h2>

          <dl class="mt-4 space-y-4 text-sm">
            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Dirección</dt>
              <dd class="mt-0.5 text-slate-800">{{ branding.center.address || '—' }}</dd>
            </div>

            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Teléfono</dt>
              <dd class="mt-0.5">
                <a
                  v-if="branding.center.phone"
                  :href="`tel:${branding.center.phone.replace(/\s/g, '')}`"
                  class="font-medium text-brand-700 hover:underline"
                >
                  {{ branding.center.phone }}
                </a>
                <span v-else class="text-slate-500">—</span>
              </dd>
              <dd v-if="whatsapp" class="mt-1">
                <a
                  :href="whatsapp"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:underline"
                >
                  <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.1 14.1c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .1-1.6-.1a12 12 0 0 1-3.4-1.9 12.9 12.9 0 0 1-2.6-3.2c-.5-.9-.6-1.7-.5-2.3.1-.5.4-.9.7-1.2.2-.2.4-.3.6-.3h.5c.2 0 .4 0 .5.4l.7 1.7c.1.2 0 .4-.1.5l-.4.5c-.1.2-.3.3-.1.6.5.9 1.1 1.6 1.9 2.2.4.3.7.5 1 .6.2.1.4.1.5-.1l.6-.7c.2-.2.3-.2.6-.1l1.6.8c.3.1.4.2.4.4v.9Z" />
                  </svg>
                  Escribir por WhatsApp
                </a>
              </dd>
            </div>

            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Correo electrónico</dt>
              <dd class="mt-0.5">
                <a
                  v-if="branding.center.email"
                  :href="`mailto:${branding.center.email}`"
                  class="font-medium break-all text-brand-700 hover:underline"
                >
                  {{ branding.center.email }}
                </a>
                <span v-else class="text-slate-500">—</span>
              </dd>
            </div>
          </dl>
        </div>

        <div class="card p-5">
          <h2 class="text-sm font-bold text-slate-900">Horario de atención</h2>
          <ul v-if="schedule.length" class="mt-3 space-y-1 text-sm text-slate-600">
            <li v-for="line in schedule" :key="line">{{ line }}</li>
          </ul>
          <p v-else class="mt-3 text-sm text-slate-500">Consúltenos el horario de atención.</p>
        </div>
      </aside>

      <!-- ---------------------------------------------------------- formulario -->
      <div class="card p-6 lg:col-span-3">
        <h2 class="text-sm font-bold text-slate-900">Escríbanos</h2>
        <p class="mt-1 text-xs text-slate-500">
          Responderemos al correo que indique. Los campos marcados con * son obligatorios.
        </p>

        <AlertMessage v-if="sent" variant="success" class="mt-4">{{ sent }}</AlertMessage>
        <AlertMessage v-if="failed" variant="error" class="mt-4">{{ failed }}</AlertMessage>

        <form class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2" novalidate @submit.prevent="submit">
          <FormField v-slot="{ id, hasError }" label="Nombre" :error="errors.name" required>
            <input :id="id" v-model="form.name" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" autocomplete="name" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Correo electrónico" :error="errors.email" required>
            <input :id="id" v-model="form.email" type="email" class="field-input"
                   :class="{ 'field-input-error': hasError }" autocomplete="email" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Teléfono" :error="errors.phone" hint="Opcional.">
            <input :id="id" v-model="form.phone" type="tel" class="field-input"
                   :class="{ 'field-input-error': hasError }" autocomplete="tel">
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Asunto" :error="errors.subject" required>
            <input :id="id" v-model="form.subject" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }"
                   placeholder="Ej. Cotización para 12 operarios" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" class="sm:col-span-2" label="Mensaje" :error="errors.message" required>
            <textarea :id="id" v-model="form.message" rows="5" class="field-input"
                      :class="{ 'field-input-error': hasError }"
                      placeholder="Cuéntenos qué necesita y le respondemos con la disponibilidad."></textarea>
          </FormField>

          <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="btn-primary" :disabled="sending">
              {{ sending ? 'Enviando…' : 'Enviar mensaje' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
