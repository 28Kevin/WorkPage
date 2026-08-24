<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { useCatalogStore } from '@/stores/catalogs'

const route = useRoute()
const router = useRouter()
const catalogs = useCatalogStore()

const today = new Date().toISOString().slice(0, 10)

/** La misma vista crea y corrige: con :id en la ruta entra en modo edición. */
const examId = computed(() => route.params.id || null)
const isEditing = computed(() => Boolean(examId.value))

const form = reactive({
  // A. Datos de la evaluación
  exam_date: today,
  exam_type: 'ingreso',
  city_id: '',

  // B. Identificación del trabajador
  full_name: '',
  document_type: 'CC',
  document_number: '',
  birth_date: '',
  sex: '',
  height_cm: '',
  weight_kg: '',
  eps_id: '',
  arl_id: '',
  afp_id: '',

  // C. Empleador
  is_independent: false,
  company_name: '',
  company_nit: '',
  client_company: '',
  economic_activity: '',
  position: '',

  // D. Examen físico
  vitals: { systolic: '', diastolic: '', heart_rate: '', respiratory_rate: '', temperature: '', spo2: '' },
  vision: { right_eye: '', left_eye: '', optical_correction: false },
  systems: {},
  assessments: {},
  clinical_findings: '',

  // E. Paraclínicos
  paraclinicals: {},

  // F. Concepto de aptitud
  aptitude_position: 'APTO',
  aptitude_heights: 'APTO',
  aptitude_confined: 'APTO',
  restrictions: '',
  restrictions_validity: '',

  // G. Consentimiento informado
  consent_accepted: true,
})

const errors = ref({})
const message = ref(null)
const ready = ref(false)
const submitting = ref(false)
const nextOrder = ref(null)
const idealWeight = ref(null)
const draftLoaded = ref(false)
const loadingDraft = ref(false)
const loadedExam = ref(null)

const selectedArl = computed(() => catalogs.findArl(form.arl_id))
const selectedCity = computed(() => catalogs.findCity(form.city_id))

onMounted(async () => {
  await catalogs.load()
  initBlocks()

  if (isEditing.value) {
    await loadExam()
  } else {
    try {
      const { data } = await api.get('/exams/next-order-number')
      nextOrder.value = data
    } catch {
      nextOrder.value = null
    }
  }

  ready.value = true
})

/** Los bloques semiestructurados se arman desde las definiciones del backend. */
function initBlocks() {
  catalogs.systems.forEach((system) => {
    if (!form.systems[system.key]) form.systems[system.key] = 'normal'
  })

  catalogs.assessments.forEach((assessment) => {
    if (form.assessments[assessment.key] === undefined) form.assessments[assessment.key] = ''
  })

  catalogs.paraclinicals.forEach((paraclinical) => {
    if (!form.paraclinicals[paraclinical.key]) {
      form.paraclinicals[paraclinical.key] = { performed: true, status: 'normal', result: '' }
    }
  })
}

async function loadExam() {
  try {
    const { data } = await api.get(`/exams/${examId.value}`)
    const exam = data.data

    loadedExam.value = exam

    Object.assign(form, {
      exam_date: exam.exam.exam_date,
      exam_type: exam.exam.exam_type,
      city_id: exam.occupational.city?.id ?? '',

      full_name: exam.worker.full_name,
      document_type: exam.worker.document_type || 'CC',
      document_number: exam.worker.document_number,
      birth_date: exam.worker.birth_date,
      sex: exam.worker.sex || '',
      height_cm: exam.worker.height_cm,
      weight_kg: exam.worker.weight_kg,
      eps_id: exam.occupational.eps?.id ?? '',
      arl_id: exam.occupational.arl?.id ?? '',
      afp_id: exam.occupational.afp?.id ?? '',

      is_independent: exam.occupational.is_independent,
      company_name: exam.occupational.company_name,
      company_nit: exam.occupational.company_nit || '',
      client_company: exam.occupational.client_company || '',
      economic_activity: exam.occupational.economic_activity || '',
      position: exam.occupational.position,

      clinical_findings: exam.exam.clinical_findings || '',
      restrictions: exam.exam.restrictions || '',
      restrictions_validity: exam.exam.restrictions_validity || '',
      consent_accepted: exam.exam.consent_accepted,
    })

    Object.assign(form.vitals, exam.medical_parameters.vitals)
    Object.assign(form.vision, exam.medical_parameters.vision)
    Object.assign(form.systems, exam.medical_parameters.systems)
    Object.assign(form.assessments, exam.medical_parameters.assessments)

    exam.aptitudes.forEach((aptitude) => {
      form[aptitude.key] = aptitude.value || 'APTO'
    })

    exam.paraclinicals.forEach((paraclinical) => {
      form.paraclinicals[paraclinical.key] = {
        performed: paraclinical.performed,
        status: paraclinical.status || 'normal',
        result: paraclinical.result || '',
      }
    })

    // Ya tiene valores reales: no hay que precargar nada encima.
    draftLoaded.value = true
  } catch (err) {
    message.value = parseApiError(err).message
  }
}

// El peso adecuado y los valores normales se calculan a partir de la estatura.
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

    if (!draftLoaded.value) loadDraft()
  },
)

// Un trabajador independiente no tiene NIT que registrar.
watch(
  () => form.is_independent,
  (independent) => {
    if (independent) form.company_nit = ''
  },
)

/** Trae del backend un juego de valores dentro de rangos normales. */
async function loadDraft() {
  const height = Number(form.height_cm)

  if (!height || height < 120 || height > 230) return

  loadingDraft.value = true

  try {
    const { data } = await api.get('/exams/draft', {
      params: { height_cm: height, weight_kg: form.weight_kg || undefined },
    })

    Object.assign(form.vitals, data.medical_parameters.vitals)
    Object.assign(form.vision, data.medical_parameters.vision)
    Object.assign(form.systems, data.medical_parameters.systems)
    Object.assign(form.assessments, data.medical_parameters.assessments)

    Object.entries(data.paraclinicals).forEach(([key, value]) => {
      form.paraclinicals[key] = { ...value }
    })

    draftLoaded.value = true
  } catch {
    // Sin borrador el médico diligencia a mano; el backend precarga al guardar.
  } finally {
    loadingDraft.value = false
  }
}

function useIdealWeight() {
  if (idealWeight.value) form.weight_kg = idealWeight.value.ideal_weight_kg
}

function number(value) {
  return value === '' || value === null ? null : Number(value)
}

async function submit() {
  submitting.value = true
  errors.value = {}
  message.value = null

  try {
    const payload = {
      ...form,
      height_cm: Number(form.height_cm),
      weight_kg: number(form.weight_kg),
      eps_id: number(form.eps_id),
      arl_id: Number(form.arl_id),
      afp_id: number(form.afp_id),
      city_id: Number(form.city_id),
      company_nit: form.is_independent ? null : form.company_nit || null,
      client_company: form.client_company || null,
      economic_activity: form.economic_activity || null,
      restrictions: form.restrictions || null,
      restrictions_validity: form.restrictions_validity || null,
      clinical_findings: form.clinical_findings || null,
      vitals: Object.fromEntries(
        Object.entries(form.vitals).map(([key, value]) => [key, number(value)]),
      ),
    }

    const { data } = isEditing.value
      ? await api.put(`/exams/${examId.value}`, payload)
      : await api.post('/exams', payload)

    router.push({
      name: 'admin.exams.show',
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
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">
          {{ isEditing ? 'Corregir evaluación médica ocupacional' : 'Nueva evaluación médica ocupacional' }}
        </h1>
        <p class="mt-1 text-sm text-slate-600">
          Evaluación para <strong>trabajo en alturas y espacios confinados</strong>. El examen físico y los
          paraclínicos vienen precargados con valores normales; revíselos antes de emitir.
        </p>
      </div>

      <div v-if="nextOrder" class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-right">
        <p class="text-xs font-medium tracking-wide text-brand-700 uppercase">Próxima orden</p>
        <p class="font-mono text-sm font-bold text-brand-900">{{ nextOrder.next_order_code }}</p>
      </div>

      <div v-else-if="loadedExam" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-right">
        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Orden</p>
        <p class="font-mono text-sm font-bold text-slate-800">{{ loadedExam.order_code }}</p>
      </div>
    </div>

    <LoadingSpinner v-if="!ready" label="Cargando…" />

    <form v-else class="space-y-5" novalidate @submit.prevent="submit">
      <AlertMessage v-if="message" variant="error" title="No se pudo guardar la evaluación">
        {{ message }}
      </AlertMessage>

      <AlertMessage v-if="isEditing" variant="info">
        Se conservan el consecutivo <strong>{{ loadedExam?.order_code }}</strong> y el código de verificación,
        así que los PDF ya impresos siguen validando.
      </AlertMessage>

      <!-- ================================================ A. Datos de la evaluación -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">A</span>
          <h2 class="text-sm font-bold text-slate-900">Datos de la evaluación</h2>
        </header>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <FormField v-slot="{ id, hasError }" label="Fecha de evaluación" :error="errors.exam_date" required>
            <input :id="id" v-model="form.exam_date" type="date" :max="today" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Tipo de evaluación" :error="errors.exam_type" required>
            <select :id="id" v-model="form.exam_type" class="field-input"
                    :class="{ 'field-input-error': hasError }" required>
              <option v-for="type in catalogs.examTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Ciudad / municipio" :error="errors.city_id"
                     :hint="selectedCity?.department ? `Departamento: ${selectedCity.department}` : null" required>
            <select :id="id" v-model="form.city_id" class="field-input"
                    :class="{ 'field-input-error': hasError }" required>
              <option value="" disabled>Seleccione una ciudad…</option>
              <option v-for="item in catalogs.cities" :key="item.id" :value="item.id">
                {{ item.name }}{{ item.department ? ` — ${item.department}` : '' }}
              </option>
            </select>
          </FormField>
        </div>
      </section>

      <!-- ========================================= B. Identificación del trabajador -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">B</span>
          <h2 class="text-sm font-bold text-slate-900">Identificación del trabajador</h2>
        </header>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <FormField v-slot="{ id, hasError }" class="sm:col-span-2 lg:col-span-3" label="Nombres y apellidos"
                     :error="errors.full_name" required>
            <input :id="id" v-model="form.full_name" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Nombres y apellidos" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Tipo de documento" :error="errors.document_type" required>
            <select :id="id" v-model="form.document_type" class="field-input"
                    :class="{ 'field-input-error': hasError }" required>
              <option v-for="type in catalogs.documentTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Número de documento" :error="errors.document_number" required>
            <input :id="id" v-model="form.document_number" type="text" inputmode="numeric" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Sin puntos ni comas" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Sexo" :error="errors.sex" required>
            <select :id="id" v-model="form.sex" class="field-input"
                    :class="{ 'field-input-error': hasError }" required>
              <option value="" disabled>Seleccione…</option>
              <option v-for="item in catalogs.sexes" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Fecha de nacimiento" :error="errors.birth_date" required>
            <input :id="id" v-model="form.birth_date" type="date" :max="today" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="EPS / entidad de salud" :error="errors.eps_id"
                     hint="Puede dejarse en blanco.">
            <select :id="id" v-model="form.eps_id" class="field-input" :class="{ 'field-input-error': hasError }">
              <option value="">Sin registrar</option>
              <option v-for="item in catalogs.eps" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="ARL" :error="errors.arl_id" required>
            <select :id="id" v-model="form.arl_id" class="field-input"
                    :class="{ 'field-input-error': hasError }" required>
              <option value="" disabled>Seleccione una ARL…</option>
              <option v-for="item in catalogs.arls" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="AFP" :error="errors.afp_id" hint="Opcional.">
            <select :id="id" v-model="form.afp_id" class="field-input" :class="{ 'field-input-error': hasError }">
              <option value="">Sin registrar</option>
              <option v-for="item in catalogs.afps" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Estatura (cm)" :error="errors.height_cm" required>
            <input :id="id" v-model="form.height_cm" type="number" min="120" max="230" step="1"
                   class="field-input" :class="{ 'field-input-error': hasError }" placeholder="175" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Peso registrado (kg)" :error="errors.weight_kg"
                     hint="Si se deja vacío se usa el peso adecuado calculado.">
            <input :id="id" v-model="form.weight_kg" type="number" min="30" max="250" step="0.1"
                   class="field-input" :class="{ 'field-input-error': hasError }" placeholder="Automático">
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
        </div>
      </section>

      <!-- ================================================================ C. Empleador -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">C</span>
          <h2 class="text-sm font-bold text-slate-900">Empleador</h2>
        </header>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <div class="sm:col-span-2 lg:col-span-3">
            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5">
              <input v-model="form.is_independent" type="checkbox"
                     class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
              <span class="text-sm text-slate-700">
                Trabajador independiente
                <span class="text-slate-500">— no se registra NIT</span>
              </span>
            </label>
          </div>

          <FormField
            v-slot="{ id, hasError }"
            :class="form.is_independent ? 'sm:col-span-2 lg:col-span-3' : 'sm:col-span-2'"
            :label="form.is_independent ? 'Nombre del contratante o del propio trabajador' : 'Razón social / empleador'"
            :error="errors.company_name"
            required
          >
            <input :id="id" v-model="form.company_name" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-if="!form.is_independent" v-slot="{ id, hasError }" label="NIT"
                     :error="errors.company_nit" required>
            <input :id="id" v-model="form.company_nit" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="900.123.456-7">
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Empresa usuaria" :error="errors.client_company"
                     hint="Solo si el trabajador presta servicios a un tercero.">
            <input :id="id" v-model="form.client_company" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Opcional">
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Actividad económica" :error="errors.economic_activity">
            <input :id="id" v-model="form.economic_activity" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Ej. Construcción de edificios">
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Cargo / ocupación" :error="errors.position" required>
            <input :id="id" v-model="form.position" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="Ej. Oficial de obra" required>
          </FormField>
        </div>
      </section>

      <!-- ============================================================ D. Examen físico -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">D</span>
          <h2 class="text-sm font-bold text-slate-900">Examen físico</h2>
          <button type="button" class="btn-ghost ml-auto py-1 text-xs" :disabled="loadingDraft || !form.height_cm"
                  @click="loadDraft">
            {{ loadingDraft ? 'Precargando…' : 'Precargar valores normales' }}
          </button>
        </header>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <FormField v-slot="{ id }" label="Presión sistólica (mmHg)" :error="errors['vitals.systolic']">
            <input :id="id" v-model="form.vitals.systolic" type="number" min="70" max="220" class="field-input">
          </FormField>

          <FormField v-slot="{ id }" label="Presión diastólica (mmHg)" :error="errors['vitals.diastolic']">
            <input :id="id" v-model="form.vitals.diastolic" type="number" min="40" max="140" class="field-input">
          </FormField>

          <FormField v-slot="{ id }" label="Frecuencia cardíaca (lpm)" :error="errors['vitals.heart_rate']">
            <input :id="id" v-model="form.vitals.heart_rate" type="number" min="35" max="180" class="field-input">
          </FormField>

          <FormField v-slot="{ id }" label="Frecuencia respiratoria (rpm)" :error="errors['vitals.respiratory_rate']">
            <input :id="id" v-model="form.vitals.respiratory_rate" type="number" min="8" max="40" class="field-input">
          </FormField>

          <FormField v-slot="{ id }" label="Temperatura (°C)" :error="errors['vitals.temperature']">
            <input :id="id" v-model="form.vitals.temperature" type="number" min="34" max="42" step="0.1"
                   class="field-input">
          </FormField>

          <FormField v-slot="{ id }" label="SpO₂ (%)" :error="errors['vitals.spo2']">
            <input :id="id" v-model="form.vitals.spo2" type="number" min="70" max="100" class="field-input">
          </FormField>

          <FormField v-slot="{ id }" label="Agudeza visual ojo derecho" :error="errors['vision.right_eye']">
            <input :id="id" v-model="form.vision.right_eye" type="text" class="field-input" placeholder="20/20">
          </FormField>

          <FormField v-slot="{ id }" label="Agudeza visual ojo izquierdo" :error="errors['vision.left_eye']">
            <input :id="id" v-model="form.vision.left_eye" type="text" class="field-input" placeholder="20/20">
          </FormField>

          <div class="flex items-end">
            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5">
              <input v-model="form.vision.optical_correction" type="checkbox"
                     class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
              <span class="text-sm text-slate-700">Usa corrección óptica</span>
            </label>
          </div>
        </div>

        <div class="border-t border-slate-200 p-5">
          <p class="field-label">Sistemas revisados</p>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div
              v-for="system in catalogs.systems"
              :key="system.key"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2"
            >
              <span class="text-sm text-slate-700">{{ system.label }}</span>

              <div class="flex gap-1">
                <button
                  type="button"
                  class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                  :class="form.systems[system.key] === 'normal'
                    ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                    : 'text-slate-500 hover:bg-slate-100'"
                  @click="form.systems[system.key] = 'normal'"
                >
                  {{ system.normal_label }}
                </button>
                <button
                  type="button"
                  class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                  :class="form.systems[system.key] === 'hallazgos'
                    ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-300'
                    : 'text-slate-500 hover:bg-slate-100'"
                  @click="form.systems[system.key] = 'hallazgos'"
                >
                  {{ system.findings_label }}
                </button>
              </div>
            </div>
          </div>

          <FormField v-slot="{ id }" class="mt-4" label="Hallazgos clínicos relevantes"
                     :error="errors.clinical_findings">
            <textarea :id="id" v-model="form.clinical_findings" rows="2" class="field-input"
                      placeholder="Describa los hallazgos si algún sistema quedó marcado como alterado."></textarea>
          </FormField>
        </div>
      </section>

      <!-- ============================================================= E. Paraclínicos -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">E</span>
          <h2 class="text-sm font-bold text-slate-900">Paraclínicos</h2>
        </header>

        <div class="space-y-2 p-5">
          <div
            v-for="paraclinical in catalogs.paraclinicals"
            :key="paraclinical.key"
            class="grid grid-cols-1 items-center gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-12"
          >
            <span class="text-sm font-medium text-slate-800 sm:col-span-3">{{ paraclinical.label }}</span>

            <label class="flex items-center gap-2 text-sm text-slate-600 sm:col-span-2">
              <input v-model="form.paraclinicals[paraclinical.key].performed" type="checkbox"
                     class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
              Realizado
            </label>

            <select
              v-model="form.paraclinicals[paraclinical.key].status"
              class="field-input sm:col-span-3"
              :disabled="!form.paraclinicals[paraclinical.key].performed"
            >
              <option value="normal">Normal</option>
              <option value="alterada">Alterada</option>
            </select>

            <input
              v-model="form.paraclinicals[paraclinical.key].result"
              type="text"
              class="field-input sm:col-span-4"
              placeholder="Resultado"
              :disabled="!form.paraclinicals[paraclinical.key].performed"
            >
          </div>
        </div>
      </section>

      <!-- ======================================================= F. Concepto de aptitud -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">F</span>
          <h2 class="text-sm font-bold text-slate-900">Concepto de aptitud</h2>
        </header>

        <div class="space-y-4 p-5">
          <p class="text-xs text-slate-500">
            El concepto global del certificado es el más restrictivo de los tres.
          </p>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <FormField v-slot="{ id, hasError }" label="Cargo / ocupación" :error="errors.aptitude_position" required>
              <select :id="id" v-model="form.aptitude_position" class="field-input"
                      :class="{ 'field-input-error': hasError }" required>
                <option v-for="item in catalogs.aptitudeResults" :key="item.value" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </FormField>

            <FormField v-slot="{ id, hasError }" label="Trabajo en alturas" :error="errors.aptitude_heights" required>
              <select :id="id" v-model="form.aptitude_heights" class="field-input"
                      :class="{ 'field-input-error': hasError }" required>
                <option v-for="item in catalogs.aptitudeResults" :key="item.value" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </FormField>

            <FormField v-slot="{ id, hasError }" label="Espacios confinados" :error="errors.aptitude_confined" required>
              <select :id="id" v-model="form.aptitude_confined" class="field-input"
                      :class="{ 'field-input-error': hasError }" required>
                <option v-for="item in catalogs.aptitudeResults" :key="item.value" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </FormField>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <FormField v-slot="{ id }" class="sm:col-span-2" label="Recomendaciones / restricciones"
                       :error="errors.restrictions">
              <textarea :id="id" v-model="form.restrictions" rows="2" class="field-input"
                        placeholder="Deje vacío si no hay restricciones."></textarea>
            </FormField>

            <FormField v-slot="{ id }" label="Temporalidad" :error="errors.restrictions_validity"
                       hint="Ej. 6 meses, permanente.">
              <input :id="id" v-model="form.restrictions_validity" type="text" class="field-input"
                     placeholder="No aplica">
            </FormField>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField
              v-for="assessment in catalogs.assessments"
              :key="assessment.key"
              v-slot="{ id }"
              :label="assessment.label"
              :error="errors[`assessments.${assessment.key}`]"
            >
              <input :id="id" v-model="form.assessments[assessment.key]" type="text" class="field-input">
            </FormField>
          </div>
        </div>
      </section>

      <!-- =================================================== G. Consentimiento informado -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">G</span>
          <h2 class="text-sm font-bold text-slate-900">Consentimiento informado</h2>
        </header>

        <div class="p-5">
          <p class="mb-3 text-xs leading-relaxed text-slate-600">
            El trabajador declara que recibió información clara sobre el propósito de la evaluación médica
            ocupacional, las pruebas complementarias indicadas, su finalidad, procedimiento, posibles molestias
            o riesgos, beneficios y el manejo confidencial de la información.
          </p>

          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-semibold transition"
              :class="form.consent_accepted
                ? 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-300'
                : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
              @click="form.consent_accepted = true"
            >
              Acepto / consiento
            </button>
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-semibold transition"
              :class="!form.consent_accepted
                ? 'bg-red-100 text-red-900 ring-1 ring-red-300'
                : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
              @click="form.consent_accepted = false"
            >
              No acepto / me niego
            </button>
          </div>

          <p v-if="errors.consent_accepted" class="field-error">{{ errors.consent_accepted[0] }}</p>
        </div>
      </section>

      <div class="flex flex-wrap items-center justify-end gap-3">
        <RouterLink
          :to="isEditing ? { name: 'admin.exams.show', params: { id: examId } } : { name: 'admin.exams' }"
          class="btn-secondary"
        >
          Cancelar
        </RouterLink>
        <button type="submit" class="btn-primary" :disabled="submitting">
          <template v-if="submitting">Guardando…</template>
          <template v-else>{{ isEditing ? 'Guardar correcciones' : 'Generar evaluación y PDF' }}</template>
        </button>
      </div>
    </form>
  </div>
</template>
