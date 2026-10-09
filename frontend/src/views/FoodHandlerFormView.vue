<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { prepareImage } from '@/services/images'
import { openFoodHandlerPdf } from '@/services/pdf'

const route = useRoute()
const router = useRouter()

const today = new Date().toISOString().slice(0, 10)

/** La misma vista crea y corrige: con :id en la ruta entra en modo edición. */
const recordId = computed(() => route.params.id || null)
const isEditing = computed(() => Boolean(recordId.value))

/** Ancho máximo de la foto: en el carné se imprime a unos 2 cm. */
const MAX_PHOTO_PX = 600

const form = reactive({
  issue_date: today,
  full_name: '',
  document_number: '',
  age: '',
  photo: null,
  // panel > grupo > campo, con la misma forma que espera la API.
  lab_results: {},
})

/** Catálogo de exámenes: etiquetas y orden salen del backend. */
const catalog = ref([])

const errors = ref({})
const message = ref(null)
const ready = ref(false)
const submitting = ref(false)
const photoError = ref(null)
const previewing = ref(false)

onMounted(async () => {
  try {
    // El borrador trae el catálogo y los valores normales ya precargados.
    const { data } = await api.get('/food-handler/draft')

    catalog.value = data.lab_catalog
    form.lab_results = data.lab_results

    if (isEditing.value) await loadRecord()
  } catch (err) {
    message.value = parseApiError(err).message
  } finally {
    ready.value = true
  }
})

async function loadRecord() {
  const { data } = await api.get(`/food-handler/${recordId.value}`)
  const record = data.data

  Object.assign(form, {
    issue_date: record.issue_date,
    full_name: record.patient.full_name,
    document_number: record.patient.document_number,
    age: record.patient.age,
    photo: record.patient.photo || null,
  })

  // Los valores guardados reemplazan la precarga, campo por campo.
  record.lab_results.forEach((panel) => {
    panel.groups.forEach((group) => {
      group.fields.forEach((field) => {
        form.lab_results[panel.key][group.key][field.key] = field.value
      })
    })
  })
}

/** La foto es opcional; se reduce antes de mandarla en el JSON. */
async function pickPhoto(event) {
  const file = event.target.files?.[0]

  photoError.value = null

  if (!file) return

  try {
    form.photo = await prepareImage(file, MAX_PHOTO_PX)
  } catch (error) {
    photoError.value = error.message === 'too-large'
      ? 'La imagen supera los 5 MB. Use una más liviana.'
      : 'No fue posible leer la imagen.'
  } finally {
    event.target.value = ''
  }
}

/** Devuelve el panel a sus valores normales. */
function resetPanel(panel) {
  panel.groups.forEach((group) => {
    group.fields.forEach((field) => {
      form.lab_results[panel.key][group.key][field.key] = field.normal
    })
  })
}

function payload() {
  return { ...form, age: Number(form.age) }
}

/** Guarda y abre el PDF en otra pestaña sin salir del formulario. */
async function preview() {
  previewing.value = true
  errors.value = {}
  message.value = null

  try {
    const { data } = isEditing.value
      ? await api.put(`/food-handler/${recordId.value}`, payload())
      : await api.post('/food-handler', payload())

    // Si se creó aquí, la vista pasa a modo edición para no duplicarlo.
    if (!isEditing.value) {
      await router.replace({ name: 'admin.food.edit', params: { id: data.data.id } })
    }

    await openFoodHandlerPdf(data.data.id)
  } catch (error) {
    const parsed = parseApiError(error)
    errors.value = parsed.errors
    message.value = parsed.message
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    previewing.value = false
  }
}

async function submit() {
  submitting.value = true
  errors.value = {}
  message.value = null

  try {
    const { data } = isEditing.value
      ? await api.put(`/food-handler/${recordId.value}`, payload())
      : await api.post('/food-handler', payload())

    router.push({
      name: 'admin.food.show',
      params: { id: data.data.id },
      query: isEditing.value ? { updated: '1' } : { created: '1' },
    })
  } catch (error) {
    const parsed = parseApiError(error)
    errors.value = parsed.errors
    message.value = parsed.message
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    submitting.value = false
  }
}

/** Clave de error que devuelve la API para un campo de laboratorio. */
function fieldError(panel, group, field) {
  return errors.value[`lab_results.${panel}.${group}.${field}`]?.[0]
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-900">
        {{ isEditing ? 'Corregir paquete de manipulación de alimentos' : 'Nuevo paquete de manipulación de alimentos' }}
      </h1>
      <p class="mt-1 text-sm text-slate-600">
        Los datos del paciente llenan las cinco páginas y el carné. Cada examen viene con su valor
        normal precargado: confírmelo o corríjalo con el resultado real.
      </p>
    </div>

    <AlertMessage v-if="message" variant="error" class="mb-4">{{ message }}</AlertMessage>

    <LoadingSpinner v-if="!ready" label="Cargando formulario…" />

    <form v-else class="space-y-5" @submit.prevent="submit">
      <!-- ======================================================= Paciente -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">A</span>
          <h2 class="text-sm font-bold text-slate-900">Datos del paciente</h2>
        </header>

        <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <FormField v-slot="{ id, hasError }" label="Fecha del documento" :error="errors.issue_date" required>
            <input :id="id" v-model="form.issue_date" type="date" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Nombre completo" :error="errors.full_name" required>
            <input :id="id" v-model="form.full_name" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Nombres y apellidos" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Número de documento" :error="errors.document_number" required>
            <input :id="id" v-model="form.document_number" type="text" inputmode="numeric" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="1020304050" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Edad" :error="errors.age" required>
            <input :id="id" v-model="form.age" type="number" min="14" max="100" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="34" required>
          </FormField>

          <div class="sm:col-span-2 lg:col-span-3">
            <span class="field-label">Fotografía del paciente</span>

            <div class="flex flex-wrap items-center gap-4">
              <div class="flex h-24 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                <img v-if="form.photo" :src="form.photo" alt="Fotografía seleccionada" class="h-full w-full object-cover">
                <span v-else class="px-1 text-center text-xs text-slate-400">Sin foto</span>
              </div>

              <div class="flex flex-col items-start gap-2">
                <label class="btn-secondary cursor-pointer">
                  <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickPhoto">
                  Seleccionar fotografía
                </label>

                <button v-if="form.photo" type="button" class="btn-ghost text-xs" @click="form.photo = null">
                  Quitar fotografía
                </button>
              </div>
            </div>

            <p v-if="photoError" class="field-error">{{ photoError }}</p>
            <p v-else-if="errors.photo" class="field-error">{{ errors.photo[0] }}</p>
            <p v-else class="field-hint">
              Opcional. Se recorta al recuadro del carné.
            </p>
          </div>
        </div>
      </section>

      <!-- =================================================== Laboratorio -->
      <section v-for="(panel, index) in catalog" :key="panel.key" class="card">
        <header class="section-heading">
          <span class="section-badge">{{ index + 1 }}</span>
          <h2 class="text-sm font-bold text-slate-900">{{ panel.label }}</h2>
          <span class="ml-auto text-xs text-slate-500">Página {{ panel.page }}</span>
          <button type="button" class="btn-ghost text-xs" @click="resetPanel(panel)">
            Valores normales
          </button>
        </header>

        <div class="space-y-4 p-5">
          <div v-for="group in panel.groups" :key="group.key">
            <p v-if="panel.groups.length > 1" class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              {{ group.label }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <FormField
                v-for="field in group.fields"
                :key="field.key"
                v-slot="{ id, hasError }"
                :label="field.label"
                :error="fieldError(panel.key, group.key, field.key)"
              >
                <input
                  :id="id"
                  v-model="form.lab_results[panel.key][group.key][field.key]"
                  type="text"
                  class="field-input"
                  :class="{ 'field-input-error': hasError }"
                  :placeholder="field.normal"
                >
              </FormField>
            </div>
          </div>
        </div>
      </section>

      <div class="flex flex-wrap items-center justify-end gap-3">
        <RouterLink
          :to="isEditing ? { name: 'admin.food.show', params: { id: recordId } } : { name: 'admin.food' }"
          class="btn-secondary"
        >
          Cancelar
        </RouterLink>

        <button type="button" class="btn-secondary" :disabled="previewing || submitting" @click="preview">
          {{ previewing ? 'Generando…' : 'Guardar y previsualizar' }}
        </button>

        <button type="submit" class="btn-primary" :disabled="submitting || previewing">
          <template v-if="submitting">Guardando…</template>
          <template v-else>{{ isEditing ? 'Guardar correcciones' : 'Generar paquete' }}</template>
        </button>
      </div>
    </form>
  </div>
</template>
