<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { useCatalogStore } from '@/stores/catalogs'

const router = useRouter()
const catalogs = useCatalogStore()

const today = new Date().toISOString().slice(0, 10)

const form = reactive({
  full_name: '',
  document_number: '',
  birth_date: '',
  email: '',
  phone: '',
  height_cm: '',
  weight_kg: '',
  company_name: '',
  company_nit: '',
  eps_id: '',
  arl_id: '',
  city_id: '',
  position: '',
  risk_ids: [],
  exam_date: today,
  exam_type: 'ingreso',
})

const errors = ref({})
const message = ref(null)
const submitting = ref(false)
const nextOrder = ref(null)
const idealWeight = ref(null)

const selectedArl = computed(() => catalogs.findArl(form.arl_id))

onMounted(async () => {
  await catalogs.load()

  try {
    const { data } = await api.get('/exams/next-order-number')
    nextOrder.value = data
  } catch {
    nextOrder.value = null
  }
})

// El peso adecuado se calcula automáticamente a partir de la estatura.
watch(
  () => form.height_cm,
  async (height) => {
    const value = Number(height)

    if (!value || value < 120 || value > 230) {
      idealWeight.value = null
      return
    }

    try {
      const { data } = await api.get('/tools/ideal-weight', { params: { height_cm: value } })
      idealWeight.value = data
    } catch {
      idealWeight.value = null
    }
  },
)

function useIdealWeight() {
  if (idealWeight.value) {
    form.weight_kg = idealWeight.value.ideal_weight_kg
  }
}

function toggleRisk(id) {
  const index = form.risk_ids.indexOf(id)
  index === -1 ? form.risk_ids.push(id) : form.risk_ids.splice(index, 1)
}

async function submit() {
  submitting.value = true
  errors.value = {}
  message.value = null

  try {
    const payload = {
      ...form,
      height_cm: Number(form.height_cm),
      weight_kg: form.weight_kg === '' ? null : Number(form.weight_kg),
      eps_id: Number(form.eps_id),
      arl_id: Number(form.arl_id),
      city_id: Number(form.city_id),
    }

    const { data } = await api.post('/exams', payload)

    router.push({ name: 'admin.exams.show', params: { id: data.data.id }, query: { created: '1' } })
  } catch (error) {
    const parsed = parseApiError(error)
    errors.value = parsed.errors
    message.value = parsed.message
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Nuevo examen médico ocupacional</h1>
        <p class="mt-1 text-sm text-slate-600">
          Los parámetros médicos se diligencian automáticamente dentro de rangos normales.
        </p>
      </div>

      <div v-if="nextOrder" class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-right">
        <p class="text-xs font-medium tracking-wide text-brand-700 uppercase">Próxima orden</p>
        <p class="font-mono text-sm font-bold text-brand-900">{{ nextOrder.next_order_code }}</p>
      </div>
    </div>

    <LoadingSpinner v-if="catalogs.loading && !catalogs.loaded" label="Cargando catálogos…" />

    <form v-else class="space-y-5" novalidate @submit.prevent="submit">
      <AlertMessage v-if="message" variant="error" title="No se pudo generar el examen">
        {{ message }}
      </AlertMessage>

      <!-- A. Datos del trabajador -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">A</span>
          <h2 class="text-sm font-bold text-slate-900">Datos del trabajador</h2>
        </header>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <FormField v-slot="{ id, hasError }" class="sm:col-span-2 lg:col-span-3" label="Nombre completo"
                     :error="errors.full_name" required>
            <input :id="id" v-model="form.full_name" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Nombres y apellidos" required />
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Número de cédula" :error="errors.document_number" required>
            <input :id="id" v-model="form.document_number" type="text" inputmode="numeric" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Sin puntos ni comas" required />
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Fecha de nacimiento" :error="errors.birth_date" required>
            <input :id="id" v-model="form.birth_date" type="date" :max="today" class="field-input"
                   :class="{ 'field-input-error': hasError }" required />
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Número de celular" :error="errors.phone" required>
            <input :id="id" v-model="form.phone" type="tel" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="3101234567" required />
          </FormField>

          <FormField v-slot="{ id, hasError }" class="sm:col-span-2" label="Correo electrónico"
                     :error="errors.email" required>
            <input :id="id" v-model="form.email" type="email" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="trabajador@empresa.com" required />
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Estatura (cm)" :error="errors.height_cm" required>
            <input :id="id" v-model="form.height_cm" type="number" min="120" max="230" step="1"
                   class="field-input" :class="{ 'field-input-error': hasError }" placeholder="175" required />
          </FormField>

          <div v-if="idealWeight" class="sm:col-span-2 lg:col-span-3">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3">
              <div>
                <p class="text-xs font-medium tracking-wide text-brand-700 uppercase">Peso adecuado calculado</p>
                <p class="text-lg font-bold text-brand-900">{{ idealWeight.ideal_weight_kg }} kg</p>
              </div>
              <p class="text-xs text-brand-800">
                Rango saludable (IMC 18.5–24.9):
                <strong>{{ idealWeight.min_weight_kg }} – {{ idealWeight.max_weight_kg }} kg</strong>
              </p>
              <button type="button" class="btn-secondary ml-auto py-1.5 text-xs" @click="useIdealWeight">
                Usar peso calculado
              </button>
            </div>
          </div>

          <FormField v-slot="{ id, hasError }" label="Peso registrado (kg)" :error="errors.weight_kg"
                     hint="Opcional. Si se deja vacío se usa el peso adecuado calculado.">
            <input :id="id" v-model="form.weight_kg" type="number" min="30" max="250" step="0.1"
                   class="field-input" :class="{ 'field-input-error': hasError }" placeholder="Automático" />
          </FormField>
        </div>
      </section>

      <!-- B. Datos ocupacionales y de afiliación -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">B</span>
          <h2 class="text-sm font-bold text-slate-900">Datos ocupacionales y de afiliación</h2>
        </header>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <FormField v-slot="{ id, hasError }" class="sm:col-span-2" label="Empresa" :error="errors.company_name" required>
            <input :id="id" v-model="form.company_name" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Razón social" required />
          </FormField>

          <FormField v-slot="{ id, hasError }" label="NIT" :error="errors.company_nit" required>
            <input :id="id" v-model="form.company_nit" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="900.123.456-7" required />
          </FormField>

          <FormField v-slot="{ id, hasError }" label="EPS" :error="errors.eps_id" required>
            <select :id="id" v-model="form.eps_id" class="field-input" :class="{ 'field-input-error': hasError }" required>
              <option value="" disabled>Seleccione una EPS…</option>
              <option v-for="item in catalogs.eps" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="ARL" :error="errors.arl_id" required>
            <select :id="id" v-model="form.arl_id" class="field-input" :class="{ 'field-input-error': hasError }" required>
              <option value="" disabled>Seleccione una ARL…</option>
              <option v-for="item in catalogs.arls" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Ciudad" :error="errors.city_id" required>
            <select :id="id" v-model="form.city_id" class="field-input" :class="{ 'field-input-error': hasError }" required>
              <option value="" disabled>Seleccione una ciudad…</option>
              <option v-for="item in catalogs.cities" :key="item.id" :value="item.id">
                {{ item.name }}{{ item.department ? ` — ${item.department}` : '' }}
              </option>
            </select>
          </FormField>

          <div v-if="selectedArl?.certificate_url" class="sm:col-span-2 lg:col-span-3">
            <a
              :href="selectedArl.certificate_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm font-medium text-sky-800 transition hover:bg-sky-100"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><path d="M15 3h6v6" />
                <path d="M10 14 21 3" />
              </svg>
              Descargar certificado en la plataforma de {{ selectedArl.name }}
            </a>
          </div>

          <FormField v-slot="{ id, hasError }" class="sm:col-span-2 lg:col-span-3" label="Cargo del trabajador"
                     :error="errors.position" required>
            <input :id="id" v-model="form.position" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Ej. Oficial de obra" required />
          </FormField>

          <div class="sm:col-span-2 lg:col-span-3">
            <p class="field-label">Riesgos / especialidades <span class="text-red-500">*</span></p>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
              <label
                v-for="risk in catalogs.risks"
                :key="risk.id"
                class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 transition"
                :class="form.risk_ids.includes(risk.id)
                  ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-300'
                  : 'border-slate-200 bg-white hover:bg-slate-50'"
              >
                <input
                  type="checkbox"
                  class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                  :checked="form.risk_ids.includes(risk.id)"
                  @change="toggleRisk(risk.id)"
                />
                <span>
                  <span class="block text-sm font-medium text-slate-900">{{ risk.name }}</span>
                  <span v-if="risk.description" class="mt-0.5 block text-xs text-slate-500">{{ risk.description }}</span>
                </span>
              </label>
            </div>
            <p v-if="errors.risk_ids" class="field-error">{{ errors.risk_ids[0] }}</p>
          </div>
        </div>
      </section>

      <!-- C. Detalles del examen -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">C</span>
          <h2 class="text-sm font-bold text-slate-900">Detalles del examen</h2>
        </header>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
          <FormField v-slot="{ id, hasError }" label="Fecha del examen" :error="errors.exam_date" required>
            <input :id="id" v-model="form.exam_date" type="date" :max="today" class="field-input"
                   :class="{ 'field-input-error': hasError }" required />
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Tipo de examen" :error="errors.exam_type" required>
            <select :id="id" v-model="form.exam_type" class="field-input" :class="{ 'field-input-error': hasError }" required>
              <option v-for="type in catalogs.examTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </FormField>
        </div>
      </section>

      <div class="flex flex-wrap items-center justify-end gap-3">
        <RouterLink :to="{ name: 'admin.exams' }" class="btn-secondary">Cancelar</RouterLink>
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Generando documento…' : 'Generar examen y PDF' }}
        </button>
      </div>
    </form>
  </div>
</template>
