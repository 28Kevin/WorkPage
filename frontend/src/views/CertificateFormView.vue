<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { prepareImage } from '@/services/images'
import { openCertificatePdf } from '@/services/pdf'

const route = useRoute()
const router = useRouter()

const today = new Date().toISOString().slice(0, 10)

const certificateId = computed(() => route.params.id || null)
const isEditing = computed(() => Boolean(certificateId.value))

/** El logo solo se muestra en la página de confirmación, no en el PDF. */
const MAX_LOGO_PX = 400

const form = reactive({
  participant_name: '',
  document_number: '',
  course: '',
  city: 'Bogotá',
  start_date: today,
  end_date: today,
  hours: 8,
  signed_date: today,
  company_nit: '',
  company_logo: null,
})

const errors = ref({})
const message = ref(null)
const ready = ref(false)
const submitting = ref(false)
const previewing = ref(false)
const logoError = ref(null)
const nextCode = ref(null)

onMounted(async () => {
  try {
    if (isEditing.value) {
      await loadCertificate()
    } else {
      const { data } = await api.get('/certificates/next-order-number')
      nextCode.value = data.next_order_code
    }
  } catch (err) {
    message.value = parseApiError(err).message
  } finally {
    ready.value = true
  }
})

async function loadCertificate() {
  const { data } = await api.get(`/certificates/${certificateId.value}`)
  const certificate = data.data

  Object.assign(form, {
    participant_name: certificate.participant_name,
    document_number: certificate.document_number,
    course: certificate.course,
    city: certificate.city,
    start_date: certificate.start_date,
    end_date: certificate.end_date,
    hours: certificate.hours,
    signed_date: certificate.signed_date,
    company_nit: certificate.company.nit || '',
    company_logo: certificate.company.logo || null,
  })

  nextCode.value = certificate.order_code
}

async function pickLogo(event) {
  const file = event.target.files?.[0]

  logoError.value = null

  if (!file) return

  try {
    form.company_logo = await prepareImage(file, MAX_LOGO_PX, { format: 'image/png' })
  } catch (error) {
    logoError.value = error.message === 'too-large'
      ? 'La imagen supera los 5 MB. Use una más liviana.'
      : 'No fue posible leer la imagen.'
  } finally {
    event.target.value = ''
  }
}

function payload() {
  return {
    ...form,
    hours: Number(form.hours),
    company_nit: form.company_nit || null,
  }
}

async function save() {
  const { data } = isEditing.value
    ? await api.put(`/certificates/${certificateId.value}`, payload())
    : await api.post('/certificates', payload())

  return data.data
}

/** Guarda y abre el certificado en otra pestaña sin salir del formulario. */
async function preview() {
  previewing.value = true
  errors.value = {}
  message.value = null

  try {
    const certificate = await save()

    if (!isEditing.value) {
      await router.replace({ name: 'admin.certificates.edit', params: { id: certificate.id } })
    }

    await openCertificatePdf(certificate.id)
  } catch (error) {
    reportError(error)
  } finally {
    previewing.value = false
  }
}

async function submit() {
  submitting.value = true
  errors.value = {}
  message.value = null

  try {
    const certificate = await save()

    router.push({
      name: 'admin.certificates',
      query: { saved: certificate.order_code },
    })
  } catch (error) {
    reportError(error)
  } finally {
    submitting.value = false
  }
}

function reportError(error) {
  const parsed = parseApiError(error)

  errors.value = parsed.errors
  message.value = parsed.message
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">
          {{ isEditing ? 'Corregir certificado' : 'Nuevo certificado de capacitación' }}
        </h1>
        <p class="mt-1 text-sm text-slate-600">
          Al guardar se genera el certificado en PDF con el diseño oficial. El código QR lleva a la
          página pública que confirma la certificación.
        </p>
      </div>

      <div v-if="nextCode" class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-right">
        <p class="text-xs font-medium tracking-wide text-brand-700 uppercase">Consecutivo</p>
        <p class="font-mono text-sm font-bold text-brand-900">{{ nextCode }}</p>
      </div>
    </div>

    <AlertMessage v-if="message" variant="error" class="mb-4">{{ message }}</AlertMessage>

    <LoadingSpinner v-if="!ready" label="Cargando formulario…" />

    <form v-else class="space-y-5" @submit.prevent="submit">
      <!-- ================================================== Participante -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">A</span>
          <h2 class="text-sm font-bold text-slate-900">Participante y formación</h2>
        </header>

        <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <FormField v-slot="{ id, hasError }" label="Nombre del participante" :error="errors.participant_name" required>
            <input :id="id" v-model="form.participant_name" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Nombres y apellidos" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Número de documento" :error="errors.document_number" required>
            <input :id="id" v-model="form.document_number" type="text" inputmode="numeric" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="1082370757" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Curso" :error="errors.course" required>
            <input :id="id" v-model="form.course" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Aparejador" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Ciudad" :error="errors.city" required>
            <input :id="id" v-model="form.city" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Fecha de inicio" :error="errors.start_date" required>
            <input :id="id" v-model="form.start_date" type="date" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Fecha de finalización" :error="errors.end_date" required>
            <input :id="id" v-model="form.end_date" type="date" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Intensidad horaria" :error="errors.hours" required>
            <input :id="id" v-model="form.hours" type="number" min="1" max="1000" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField
            v-slot="{ id, hasError }"
            label="Fecha de firma"
            :error="errors.signed_date"
            hint="Es la fecha que aparece en la línea de testimonio."
            required
          >
            <input :id="id" v-model="form.signed_date" type="date" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>
        </div>
      </section>

      <!-- ======================================================= Empresa -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">B</span>
          <h2 class="text-sm font-bold text-slate-900">Empresa de la certificación</h2>
        </header>

        <div class="p-5">
          <p class="mb-4 text-sm text-slate-600">
            Estos dos datos <strong>no se imprimen en el certificado</strong>: se muestran en la
            página pública que abre el código QR.
          </p>

          <div class="grid gap-4 sm:grid-cols-2">
            <FormField v-slot="{ id, hasError }" label="NIT de la empresa" :error="errors.company_nit">
              <input :id="id" v-model="form.company_nit" type="text" class="field-input"
                     :class="{ 'field-input-error': hasError }" placeholder="901.482.951-5">
            </FormField>

            <div>
              <span class="field-label">Logo de la empresa</span>

              <div class="flex flex-wrap items-center gap-4">
                <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                  <img v-if="form.company_logo" :src="form.company_logo" alt="Logo seleccionado" class="max-h-full max-w-full object-contain">
                  <span v-else class="px-1 text-center text-xs text-slate-400">Sin logo</span>
                </div>

                <div class="flex flex-col items-start gap-2">
                  <label class="btn-secondary cursor-pointer">
                    <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="sr-only" @change="pickLogo">
                    Seleccionar logo
                  </label>

                  <button v-if="form.company_logo" type="button" class="btn-ghost text-xs" @click="form.company_logo = null">
                    Quitar logo
                  </button>
                </div>
              </div>

              <p v-if="logoError" class="field-error">{{ logoError }}</p>
              <p v-else-if="errors.company_logo" class="field-error">{{ errors.company_logo[0] }}</p>
            </div>
          </div>
        </div>
      </section>

      <div class="flex flex-wrap items-center justify-end gap-3">
        <RouterLink :to="{ name: 'admin.certificates' }" class="btn-secondary">Cancelar</RouterLink>

        <button type="button" class="btn-secondary" :disabled="previewing || submitting" @click="preview">
          {{ previewing ? 'Generando…' : 'Guardar y previsualizar' }}
        </button>

        <button type="submit" class="btn-primary" :disabled="submitting || previewing">
          <template v-if="submitting">Guardando…</template>
          <template v-else>{{ isEditing ? 'Guardar correcciones' : 'Generar certificado' }}</template>
        </button>
      </div>
    </form>
  </div>
</template>
