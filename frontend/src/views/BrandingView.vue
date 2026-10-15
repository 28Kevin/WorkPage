<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import BackupPanel from '@/components/BackupPanel.vue'
import BillingConceptsManager from '@/components/BillingConceptsManager.vue'
import MailSettingsPanel from '@/components/MailSettingsPanel.vue'
import GalleryManager from '@/components/GalleryManager.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import { parseApiError } from '@/services/api'
import { prepareImage } from '@/services/images'
import { applyBranding, buildPalette } from '@/services/theme'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const auth = useAuthStore()
const branding = useBrandingStore()

/** Familias de Google Fonts sugeridas; el campo acepta cualquier otra. */
const FONTS = [
  'Plus Jakarta Sans', 'Source Sans 3', 'Archivo', 'Outfit', 'Sora', 'Figtree',
  'Inter', 'Roboto', 'Open Sans', 'Lato',
  'Montserrat', 'Poppins', 'Nunito Sans', 'Work Sans', 'Manrope', 'Rubik',
  'IBM Plex Sans', 'Merriweather', 'Playfair Display', 'Libre Baskerville',
]

const RADII = [
  { value: '0rem', label: 'Recto' },
  { value: '0.25rem', label: 'Apenas redondeado' },
  { value: '0.5rem', label: 'Redondeado' },
  { value: '0.75rem', label: 'Redondeado amplio' },
  { value: '1rem', label: 'Muy redondeado' },
  { value: '1.5rem', label: 'Píldora' },
]

const MAX_LOGO_PX = 320

const form = reactive({
  identity: { app_name: '', tagline: '', logo: null },
  theme: {
    brand_color: '#2563eb',
    accent_color: '#0284c7',
    font_heading: '',
    font_body: '',
    radius: '0.75rem',
  },
  center: {
    name: '', nit: '', address: '',
    phone: '', email: '', physician_name: '', schedule: '',
  },
  // Emisor de las cuentas de cobro.
  biller: {
    name: '', nit: '', document: '', mobile: '', email: '', signature: null,
  },
  // Textos fijos del paquete de manipulacion de alimentos.
  food_handler: {
    course_name: '', course_hours: '', training_resolution: '',
    card_authorization: '', bacteriologist_name: '', food_engineer_name: '',
  },
})

const ready = ref(false)
const errors = ref({})
const alert = ref(null)
const logoError = ref(null)
const signatureError = ref(null)
const dirty = ref(false)

const previewPalette = computed(() => buildPalette(form.theme.brand_color))

onMounted(async () => {
  await branding.load()
  fill(branding.branding)
  ready.value = true
})

// La vista previa se aplica en vivo; al salir sin guardar se descarta.
watch(
  () => [
    form.theme.brand_color,
    form.theme.accent_color,
    form.theme.font_heading,
    form.theme.font_body,
    form.theme.radius,
  ],
  () => {
    if (!ready.value) return

    dirty.value = true

    applyBranding({
      theme: {
        ...form.theme,
        palette: buildPalette(form.theme.brand_color),
        accent_palette: buildPalette(form.theme.accent_color),
      },
    })
  },
)

onBeforeRouteLeave(() => {
  if (dirty.value) branding.restore()
})

function fill(value) {
  if (!value) return

  Object.assign(form.identity, value.identity)
  Object.assign(form.center, value.center)
  if (value.food_handler) Object.assign(form.food_handler, value.food_handler)
  if (value.biller) Object.assign(form.biller, value.biller)
  Object.assign(form.theme, {
    brand_color: value.theme.brand_color,
    accent_color: value.theme.accent_color,
    font_heading: value.theme.font_heading,
    font_body: value.theme.font_body,
    radius: value.theme.radius,
  })
}

/** Reduce el logo antes de mandarlo: viaja en base64 dentro del JSON. */
async function handleLogo(event) {
  const file = event.target.files?.[0]

  logoError.value = null

  if (!file) return

  try {
    // PNG para conservar la transparencia del logo.
    form.identity.logo = await prepareImage(file, MAX_LOGO_PX, { format: 'image/png' })
    dirty.value = true
  } catch (error) {
    logoError.value = error.message === 'too-large'
      ? 'La imagen supera los 5 MB. Use una más liviana.'
      : 'No fue posible leer la imagen.'
  } finally {
    event.target.value = ''
  }
}

function removeLogo() {
  form.identity.logo = null
  dirty.value = true
}

/** Firma del emisor: va en base64 dentro del JSON, como el logo. */
async function handleSignature(event) {
  const file = event.target.files?.[0]

  signatureError.value = null

  if (!file) return

  try {
    form.biller.signature = await prepareImage(file, 600, { format: 'image/png' })
    dirty.value = true
  } catch (error) {
    signatureError.value = error.message === 'too-large'
      ? 'La imagen supera los 5 MB. Use una más liviana.'
      : 'No fue posible leer la imagen.'
  } finally {
    event.target.value = ''
  }
}

async function submit() {
  errors.value = {}
  alert.value = null

  try {
    const message = await branding.save({
      identity: { ...form.identity },
      theme: { ...form.theme },
      center: { ...form.center },
      food_handler: { ...form.food_handler },
      biller: { ...form.biller },
    })

    dirty.value = false
    alert.value = { variant: 'success', text: message }
  } catch (error) {
    const parsed = parseApiError(error)

    errors.value = parsed.errors
    alert.value = { variant: 'error', text: parsed.message }
  }
}

function discard() {
  fill(branding.branding)
  branding.restore()
  dirty.value = false
  errors.value = {}
  alert.value = null
}
</script>

<template>
  <div v-if="!ready" class="flex justify-center py-16">
    <LoadingSpinner />
  </div>

  <form v-else class="space-y-6" @submit.prevent="submit">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Configuración</h1>
        <p class="mt-1 text-sm text-slate-600">
          Identidad visual y datos del centro. Los cambios se aplican en toda la plataforma
          y en los certificados emitidos.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button v-if="dirty" type="button" class="btn-secondary" @click="discard">Descartar</button>
        <button type="submit" class="btn-primary" :disabled="branding.saving">
          {{ branding.saving ? 'Guardando…' : 'Guardar cambios' }}
        </button>
      </div>
    </header>

    <AlertMessage v-if="alert" :variant="alert.variant">{{ alert.text }}</AlertMessage>

    <AlertMessage v-if="dirty" variant="info">
      Está viendo una vista previa. Los cambios no quedan guardados hasta que confirme.
    </AlertMessage>

    <!-- ------------------------------------------------------------ identidad -->
    <section class="card">
      <div class="section-heading">
        <span class="section-badge">1</span>
        <h2 class="text-sm font-semibold text-slate-900">Identidad</h2>
      </div>

      <div class="grid gap-5 p-5 md:grid-cols-2">
        <FormField label="Nombre de la plataforma" :error="errors['identity.app_name']" required>
          <template #default="{ id }">
            <input
              :id="id"
              v-model="form.identity.app_name"
              type="text"
              class="field-input"
              maxlength="80"
              @input="dirty = true"
            >
          </template>
        </FormField>

        <FormField
          label="Eslogan"
          :error="errors['identity.tagline']"
          hint="Aparece bajo el nombre, en la cabecera."
        >
          <template #default="{ id }">
            <input
              :id="id"
              v-model="form.identity.tagline"
              type="text"
              class="field-input"
              maxlength="120"
              @input="dirty = true"
            >
          </template>
        </FormField>

        <div class="md:col-span-2">
          <span class="field-label">Logo</span>

          <div class="flex flex-wrap items-center gap-4">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50">
              <img
                v-if="form.identity.logo"
                :src="form.identity.logo"
                alt="Logo actual"
                class="max-h-16 max-w-16 object-contain"
              >
              <span v-else class="text-xs text-slate-400">Sin logo</span>
            </div>

            <div class="flex flex-col items-start gap-2">
              <label class="btn-secondary cursor-pointer">
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/svg+xml"
                  class="sr-only"
                  @change="handleLogo"
                >
                Seleccionar imagen
              </label>

              <button v-if="form.identity.logo" type="button" class="btn-ghost text-xs" @click="removeLogo">
                Quitar logo
              </button>
            </div>
          </div>

          <p v-if="logoError" class="field-error">{{ logoError }}</p>
          <p v-else-if="errors['identity.logo']" class="field-error">{{ errors['identity.logo'][0] }}</p>
          <p v-else class="field-hint">
            PNG, JPG, WEBP o SVG. Se reduce a {{ MAX_LOGO_PX }} px y se guarda dentro de la base de datos.
          </p>
        </div>
      </div>
    </section>

    <!-- ---------------------------------------------------------------- tema -->
    <section class="card">
      <div class="section-heading">
        <span class="section-badge">2</span>
        <h2 class="text-sm font-semibold text-slate-900">Tema visual</h2>
      </div>

      <div class="grid gap-5 p-5 md:grid-cols-2">
        <FormField
          label="Color de marca"
          :error="errors['theme.brand_color']"
          hint="Botones, encabezados y el certificado en PDF."
          required
        >
          <template #default="{ id }">
            <div class="flex gap-2">
              <input
                :id="id"
                v-model="form.theme.brand_color"
                type="color"
                class="h-10 w-14 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
              >
              <input v-model="form.theme.brand_color" type="text" class="field-input font-mono" maxlength="7">
            </div>
          </template>
        </FormField>

        <FormField
          label="Color de acento"
          :error="errors['theme.accent_color']"
          hint="Detalles y estados secundarios."
          required
        >
          <template #default="{ id }">
            <div class="flex gap-2">
              <input
                :id="id"
                v-model="form.theme.accent_color"
                type="color"
                class="h-10 w-14 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
              >
              <input v-model="form.theme.accent_color" type="text" class="field-input font-mono" maxlength="7">
            </div>
          </template>
        </FormField>

        <FormField
          label="Tipografía de títulos"
          :error="errors['theme.font_heading']"
          hint="Cualquier familia disponible en Google Fonts."
          required
        >
          <template #default="{ id }">
            <input :id="id" v-model="form.theme.font_heading" type="text" class="field-input" list="fuentes">
          </template>
        </FormField>

        <FormField label="Tipografía de texto" :error="errors['theme.font_body']" required>
          <template #default="{ id }">
            <input :id="id" v-model="form.theme.font_body" type="text" class="field-input" list="fuentes">
          </template>
        </FormField>

        <datalist id="fuentes">
          <option v-for="font in FONTS" :key="font" :value="font" />
        </datalist>

        <FormField label="Redondeo de bordes" :error="errors['theme.radius']" required>
          <template #default="{ id }">
            <select :id="id" v-model="form.theme.radius" class="field-input">
              <option v-for="option in RADII" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </template>
        </FormField>

        <div class="md:col-span-2">
          <span class="field-label">Paleta derivada</span>

          <div class="flex overflow-hidden rounded-lg border border-slate-200">
            <div
              v-for="(hex, shade) in previewPalette"
              :key="shade"
              class="flex h-12 flex-1 items-end justify-center pb-1 text-[10px] font-medium"
              :style="{ background: hex, color: Number(shade) >= 500 ? '#ffffff' : '#0f172a' }"
            >
              {{ shade }}
            </div>
          </div>

          <p class="field-hint">
            Se derivan diez tonos del color de marca. El certificado en PDF usa los mismos.
          </p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 md:col-span-2">
          <span class="field-label">Vista previa</span>

          <div class="flex flex-wrap items-center gap-3">
            <span class="btn-primary">Botón principal</span>
            <span class="btn-secondary">Secundario</span>
            <span class="chip">Etiqueta</span>
            <span class="section-badge">3</span>
          </div>

          <h3 class="mt-4 text-lg font-bold text-slate-900">
            {{ form.identity.app_name || 'Título de ejemplo' }}
          </h3>
          <p class="text-sm text-slate-600">
            Texto de muestra con la tipografía seleccionada, para verificar legibilidad.
          </p>
        </div>
      </div>
    </section>

    <!-- ------------------------------------------------------- centro medico -->
    <section class="card">
      <div class="section-heading">
        <span class="section-badge">3</span>
        <h2 class="text-sm font-semibold text-slate-900">Datos del centro médico</h2>
      </div>

      <p class="px-5 pt-4 text-xs text-slate-500">
        Se imprimen en el encabezado y el pie de cada certificado, y en la leyenda de verificación pública.
      </p>

      <div class="grid gap-5 p-5 md:grid-cols-2">
        <FormField label="Razón social" :error="errors['center.name']" required>
          <template #default="{ id }">
            <input :id="id" v-model="form.center.name" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="NIT" :error="errors['center.nit']" required>
          <template #default="{ id }">
            <input :id="id" v-model="form.center.nit" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Dirección" :error="errors['center.address']" required>
          <template #default="{ id }">
            <input :id="id" v-model="form.center.address" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Teléfono" :error="errors['center.phone']" required>
          <template #default="{ id }">
            <input :id="id" v-model="form.center.phone" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Correo electrónico" :error="errors['center.email']" required>
          <template #default="{ id }">
            <input :id="id" v-model="form.center.email" type="email" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField
          label="Médico responsable"
          :error="errors['center.physician_name']"
          hint="Opcional. Si se deja vacío, el certificado muestra solo el sello."
        >
          <template #default="{ id }">
            <input :id="id" v-model="form.center.physician_name" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField
          class="md:col-span-2"
          label="Horario de atención"
          :error="errors['center.schedule']"
          hint="Una línea por franja. Se muestra en Servicios y en Contáctenos."
        >
          <template #default="{ id }">
            <textarea
              :id="id"
              v-model="form.center.schedule"
              rows="2"
              class="field-input"
              @input="dirty = true"
            ></textarea>
          </template>
        </FormField>

      </div>
    </section>

    <!-- ----------------------------------------- emisor de cuentas de cobro -->
    <section class="card">
      <div class="section-heading">
        <span class="section-badge">4</span>
        <h2 class="text-sm font-semibold text-slate-900">Cuentas de cobro</h2>
      </div>

      <div class="grid gap-4 p-5 sm:grid-cols-2">
        <p class="text-sm text-slate-600 sm:col-span-2">
          Datos del emisor. Se configuran una vez y aparecen en todas las cuentas que se generen.
        </p>

        <FormField label="Nombre del emisor" :error="errors['biller.name']">
          <template #default="{ id }">
            <input :id="id" v-model="form.biller.name" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="NIT" :error="errors['biller.nit']">
          <template #default="{ id }">
            <input :id="id" v-model="form.biller.nit" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Cédula" :error="errors['biller.document']">
          <template #default="{ id }">
            <input :id="id" v-model="form.biller.document" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Teléfono celular" :error="errors['biller.mobile']">
          <template #default="{ id }">
            <input :id="id" v-model="form.biller.mobile" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Correo electrónico" :error="errors['biller.email']">
          <template #default="{ id }">
            <input :id="id" v-model="form.biller.email" type="email" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <div>
          <span class="field-label">Firma</span>

          <div class="flex flex-wrap items-center gap-4">
            <div class="flex h-20 w-40 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
              <img v-if="form.biller.signature" :src="form.biller.signature" alt="Firma del emisor" class="max-h-full max-w-full object-contain">
              <span v-else class="px-1 text-center text-xs text-slate-400">Sin firma</span>
            </div>

            <div class="flex flex-col items-start gap-2">
              <label class="btn-secondary cursor-pointer">
                <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="handleSignature">
                Seleccionar firma
              </label>

              <button
                v-if="form.biller.signature"
                type="button"
                class="btn-ghost text-xs"
                @click="form.biller.signature = null; dirty = true"
              >
                Quitar firma
              </button>
            </div>
          </div>

          <p v-if="signatureError" class="field-error">{{ signatureError }}</p>
          <p v-else-if="errors['biller.signature']" class="field-error">{{ errors['biller.signature'][0] }}</p>
        </div>
      </div>

      <div class="border-t border-slate-200">
        <BillingConceptsManager />
      </div>
    </section>

    <!-- -------------------------------------- textos del paquete de alimentos -->
    <section class="card">
      <div class="section-heading">
        <span class="section-badge">5</span>
        <h2 class="text-sm font-semibold text-slate-900">Manipulación de alimentos</h2>
      </div>

      <div class="grid gap-4 p-5 sm:grid-cols-2">
        <p class="text-sm text-slate-600 sm:col-span-2">
          Textos fijos que se imprimen en el paquete y en el carné. Al cambiarlos aquí, los
          documentos que se emitan en adelante los toman sin tocar la plantilla.
        </p>

        <FormField label="Nombre del curso" :error="errors['food_handler.course_name']">
          <template #default="{ id }">
            <input :id="id" v-model="form.food_handler.course_name" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Intensidad horaria" :error="errors['food_handler.course_hours']">
          <template #default="{ id }">
            <input :id="id" v-model="form.food_handler.course_hours" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Resolución de la capacitación" :error="errors['food_handler.training_resolution']">
          <template #default="{ id }">
            <input :id="id" v-model="form.food_handler.training_resolution" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField
          label="Autorización del carné"
          hint="Línea al pie del carné, por ejemplo la resolución que autoriza su expedición."
          :error="errors['food_handler.card_authorization']"
        >
          <template #default="{ id }">
            <input :id="id" v-model="form.food_handler.card_authorization" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField
          label="Bacterióloga"
          hint="Firma las páginas de laboratorio. Vacío deja la línea en blanco."
          :error="errors['food_handler.bacteriologist_name']"
        >
          <template #default="{ id }">
            <input :id="id" v-model="form.food_handler.bacteriologist_name" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField
          label="Ingeniera de alimentos"
          hint="Firma la capacitación y el carné."
          :error="errors['food_handler.food_engineer_name']"
        >
          <template #default="{ id }">
            <input :id="id" v-model="form.food_handler.food_engineer_name" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

      </div>
    </section>

    <!-- ------------------------------------------------------------- galería -->
    <section class="card">
      <div class="section-heading">
        <span class="section-badge">6</span>
        <h2 class="text-sm font-semibold text-slate-900">Galería de fotografías</h2>
      </div>

      <GalleryManager />
    </section>

    <!-- Correo y respaldo: solo para cuentas de desarrollo. -->
    <template v-if="auth.isDeveloper">
      <MailSettingsPanel />
      <BackupPanel />
    </template>

    <div class="flex justify-end gap-2">
      <button v-if="dirty" type="button" class="btn-secondary" @click="discard">Descartar</button>
      <button type="submit" class="btn-primary" :disabled="branding.saving">
        {{ branding.saving ? 'Guardando…' : 'Guardar cambios' }}
      </button>
    </div>
  </form>
</template>
