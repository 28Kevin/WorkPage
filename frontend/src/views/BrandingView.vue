<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import { parseApiError } from '@/services/api'
import { applyBranding, buildPalette } from '@/services/theme'
import { useBrandingStore } from '@/stores/branding'

const branding = useBrandingStore()

/** Familias de Google Fonts sugeridas; el campo acepta cualquier otra. */
const FONTS = [
  'Archivo', 'Source Sans 3', 'Inter', 'Roboto', 'Open Sans', 'Lato',
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
    name: '', nit: '', license: '', address: '',
    phone: '', email: '', physician_name: '', physician_license: '',
  },
})

const ready = ref(false)
const errors = ref({})
const alert = ref(null)
const logoError = ref(null)
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

  if (file.size > 2 * 1024 * 1024) {
    logoError.value = 'La imagen supera los 2 MB. Use una más liviana.'
    event.target.value = ''
    return
  }

  try {
    const dataUrl = await readAsDataUrl(file)

    form.identity.logo = file.type === 'image/svg+xml' ? dataUrl : await downscale(dataUrl)
    dirty.value = true
  } catch {
    logoError.value = 'No fue posible leer la imagen.'
  } finally {
    event.target.value = ''
  }
}

function readAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()

    reader.onload = () => resolve(reader.result)
    reader.onerror = () => reject(new Error('read'))
    reader.readAsDataURL(file)
  })
}

function downscale(dataUrl) {
  return new Promise((resolve, reject) => {
    const image = new Image()

    image.onload = () => {
      const scale = Math.min(1, MAX_LOGO_PX / Math.max(image.width, image.height))
      const canvas = document.createElement('canvas')

      canvas.width = Math.round(image.width * scale)
      canvas.height = Math.round(image.height * scale)
      canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height)

      resolve(canvas.toDataURL('image/png'))
    }

    image.onerror = () => reject(new Error('decode'))
    image.src = dataUrl
  })
}

function removeLogo() {
  form.identity.logo = null
  dirty.value = true
}

async function submit() {
  errors.value = {}
  alert.value = null

  try {
    const message = await branding.save({
      identity: { ...form.identity },
      theme: { ...form.theme },
      center: { ...form.center },
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

        <FormField label="Licencia" :error="errors['center.license']" required>
          <template #default="{ id }">
            <input :id="id" v-model="form.center.license" type="text" class="field-input" @input="dirty = true">
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

        <FormField label="Médico responsable" :error="errors['center.physician_name']" required>
          <template #default="{ id }">
            <input :id="id" v-model="form.center.physician_name" type="text" class="field-input" @input="dirty = true">
          </template>
        </FormField>

        <FormField label="Registro profesional" :error="errors['center.physician_license']" required>
          <template #default="{ id }">
            <input
              :id="id"
              v-model="form.center.physician_license"
              type="text"
              class="field-input"
              @input="dirty = true"
            >
          </template>
        </FormField>
      </div>
    </section>

    <div class="flex justify-end gap-2">
      <button v-if="dirty" type="button" class="btn-secondary" @click="discard">Descartar</button>
      <button type="submit" class="btn-primary" :disabled="branding.saving">
        {{ branding.saving ? 'Guardando…' : 'Guardar cambios' }}
      </button>
    </div>
  </form>
</template>
