<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { downloadExamPdf, openExamPdf } from '@/services/pdf'
import { useCatalogStore } from '@/stores/catalogs'

const route = useRoute()
const catalogs = useCatalogStore()

const exam = ref(null)
const loading = ref(true)
const error = ref(null)
const busy = ref(null)
const copied = ref(false)

const justCreated = computed(() => route.query.created === '1')
const justUpdated = computed(() => route.query.updated === '1')

// Anulacion: se pide un motivo antes de confirmar.
const annulOpen = ref(false)
const annulReason = ref('')
const annulError = ref(null)
const annulling = ref(false)

const parameterGroups = [
  { key: 'vitals', title: 'Signos vitales' },
  { key: 'anthropometry', title: 'Antropometría' },
  { key: 'vision', title: 'Agudeza visual' },
  { key: 'assessments', title: 'Resultados de valoración' },
  { key: 'history', title: 'Antecedentes' },
]

/** Colores del concepto de aptitud según su severidad. */
const RESULT_STYLES = {
  APTO: 'bg-emerald-100 text-emerald-800 ring-emerald-300',
  APTO_CON_RESTRICCIONES: 'bg-amber-100 text-amber-900 ring-amber-300',
  APLAZADO: 'bg-orange-100 text-orange-900 ring-orange-300',
  NO_APTO: 'bg-red-100 text-red-900 ring-red-300',
}

const resultStyle = computed(() => RESULT_STYLES[exam.value?.exam.result] || RESULT_STYLES.APTO)

/** Etiqueta legible del estado de cada sistema revisado. */
const systemRows = computed(() => {
  const values = exam.value?.medical_parameters?.systems || {}

  return catalogs.systems.map((system) => ({
    key: system.key,
    label: system.label,
    findings: values[system.key] === 'hallazgos',
    value: values[system.key] === 'hallazgos' ? system.findings_label : system.normal_label,
  }))
})

onMounted(async () => {
  try {
    const [{ data }] = await Promise.all([
      api.get(`/exams/${route.params.id}`),
      catalogs.load().catch(() => null),
    ])

    exam.value = data.data
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
})

async function handle(action) {
  busy.value = action
  error.value = null

  try {
    action === 'download'
      ? await downloadExamPdf(exam.value.id)
      : await openExamPdf(exam.value.id)
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    busy.value = null
  }
}

async function confirmAnnulment() {
  annulling.value = true
  annulError.value = null

  try {
    const { data } = await api.delete(`/exams/${exam.value.id}`, {
      data: { reason: annulReason.value.trim() },
    })

    exam.value = data.data
    annulOpen.value = false
  } catch (err) {
    const parsed = parseApiError(err)
    annulError.value = parsed.errors?.reason?.[0] || parsed.message
  } finally {
    annulling.value = false
  }
}

async function copyVerificationUrl() {
  try {
    await navigator.clipboard.writeText(exam.value.verification.url)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    error.value = 'No fue posible copiar el enlace al portapapeles.'
  }
}

// Las claves del JSON son técnicas; se traducen para mostrarlas.
const parameterLabels = {
  systolic: 'Presión sistólica (mmHg)',
  diastolic: 'Presión diastólica (mmHg)',
  heart_rate: 'Frecuencia cardíaca (lpm)',
  respiratory_rate: 'Frecuencia respiratoria (rpm)',
  temperature: 'Temperatura (°C)',
  spo2: 'Saturación de oxígeno (%)',
  height_cm: 'Estatura (cm)',
  weight_kg: 'Peso (kg)',
  ideal_weight_kg: 'Peso adecuado (kg)',
  healthy_range_kg: 'Rango saludable (kg)',
  bmi: 'IMC',
  bmi_classification: 'Clasificación IMC',
  right_eye: 'Ojo derecho',
  left_eye: 'Ojo izquierdo',
  optical_correction: 'Corrección óptica',
  visual: 'Valoración visual',
  hearing: 'Valoración auditiva',
  respiratory: 'Respiratorio / pulmonar',
  cardiovascular: 'Cardiovascular / neurológico',
  personal: 'Personales',
  family: 'Familiares',
  occupational: 'Ocupacionales',
  allergic: 'Alérgicos',
  surgical: 'Quirúrgicos',
}

function humanize(key) {
  if (parameterLabels[key]) return parameterLabels[key]

  const text = String(key).replace(/_/g, ' ')
  return text.charAt(0).toUpperCase() + text.slice(1)
}

function display(value) {
  if (value === true) return 'Sí'
  if (value === false) return 'No'
  if (value === null || value === '') return '—'

  return value
}

function formatDate(value) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('es-CO', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}
</script>

<template>
  <div>
    <LoadingSpinner v-if="loading" label="Cargando evaluación…" />

    <AlertMessage v-else-if="error && !exam" variant="error">{{ error }}</AlertMessage>

    <template v-else-if="exam">
      <RouterLink
        :to="{ name: 'admin.exams' }"
        class="mb-4 inline-block text-sm font-medium text-brand-700 hover:underline"
      >
        ← Volver al listado
      </RouterLink>

      <AlertMessage v-if="justCreated" variant="success" title="Evaluación generada correctamente" class="mb-5">
        Se asignó el consecutivo <strong>{{ exam.order_code }}</strong> y el documento ya puede descargarse en PDF
        con su código QR de verificación.
      </AlertMessage>

      <AlertMessage v-if="justUpdated" variant="success" title="Correcciones guardadas" class="mb-5">
        Se conservaron el consecutivo y el código de verificación, así que los PDF ya impresos siguen validando.
      </AlertMessage>

      <AlertMessage
        v-if="exam.annulment?.annulled"
        variant="error"
        title="Esta evaluación está anulada"
        class="mb-5"
      >
        Motivo: {{ exam.annulment.reason }}. El documento ya no aparece en la consulta pública y el PDF sale
        marcado como anulado.
      </AlertMessage>

      <AlertMessage v-if="error" variant="error" class="mb-5">{{ error }}</AlertMessage>

      <!-- ------------------------------------------------------------ resumen -->
      <div class="card mb-5 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4">
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
              Orden No. (consecutivo {{ exam.order_number }})
            </p>
            <p class="font-mono text-lg font-bold text-brand-800">{{ exam.order_code }}</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <RouterLink
              v-if="!exam.annulment?.annulled"
              :to="{ name: 'admin.exams.edit', params: { id: exam.id } }"
              class="btn-secondary"
            >
              Corregir
            </RouterLink>
            <button
              v-if="!exam.annulment?.annulled"
              type="button"
              class="btn-secondary text-red-700"
              @click="annulOpen = true"
            >
              Anular
            </button>
            <button type="button" class="btn-secondary" :disabled="busy === 'preview'" @click="handle('preview')">
              {{ busy === 'preview' ? 'Abriendo…' : 'Previsualizar PDF' }}
            </button>
            <button type="button" class="btn-primary" :disabled="busy === 'download'" @click="handle('download')">
              {{ busy === 'download' ? 'Descargando…' : 'Descargar PDF' }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
          <div class="lg:col-span-2">
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Trabajador</p>
            <p class="mt-0.5 font-semibold text-slate-900">{{ exam.worker.full_name }}</p>
            <p class="font-mono text-xs text-slate-500">
              {{ exam.worker.document_type || 'CC' }} {{ exam.worker.document_number }} ·
              {{ exam.worker.age }} años
              <template v-if="exam.worker.sex_label"> · {{ exam.worker.sex_label }}</template>
            </p>
          </div>
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Concepto global</p>
            <p class="mt-1">
              <span :class="['rounded-full px-3 py-1 text-sm font-bold ring-1 ring-inset', resultStyle]">
                {{ exam.exam.result_label }}
              </span>
            </p>
          </div>
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Tipo / fecha</p>
            <p class="mt-0.5 text-sm font-medium text-slate-900">{{ exam.exam.exam_type_label }}</p>
            <p class="text-xs text-slate-500">{{ formatDate(exam.exam.exam_date) }}</p>
          </div>

          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Estatura / peso adecuado</p>
            <p class="mt-0.5 text-sm text-slate-900">
              {{ exam.worker.height_cm }} cm · {{ exam.worker.ideal_weight_kg }} kg
            </p>
            <p class="text-xs text-slate-500">Registrado: {{ exam.worker.weight_kg }} kg</p>
          </div>
          <div class="lg:col-span-2">
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Empleador</p>
            <p class="mt-0.5 text-sm text-slate-900">
              {{ exam.occupational.company_name }}
              <template v-if="exam.occupational.is_independent">
                <span class="text-xs text-slate-500">— trabajador independiente</span>
              </template>
              <template v-else-if="exam.occupational.company_nit">
                — NIT {{ exam.occupational.company_nit }}
              </template>
            </p>
            <p class="text-xs text-slate-500">
              Cargo: {{ exam.occupational.position }}
              <template v-if="exam.occupational.economic_activity">
                · {{ exam.occupational.economic_activity }}
              </template>
            </p>
            <p v-if="exam.occupational.client_company" class="text-xs text-slate-500">
              Empresa usuaria: {{ exam.occupational.client_company }}
            </p>
          </div>

          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">EPS</p>
            <p class="mt-0.5 text-sm text-slate-900">{{ exam.occupational.eps?.name }}</p>
          </div>
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">ARL</p>
            <p class="mt-0.5 text-sm text-slate-900">{{ exam.occupational.arl?.name }}</p>
            <a
              v-if="exam.occupational.arl?.certificate_url"
              :href="exam.occupational.arl.certificate_url"
              target="_blank"
              rel="noopener noreferrer"
              class="text-xs font-medium text-sky-700 hover:underline"
            >
              Descargar certificado ↗
            </a>
          </div>
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">AFP</p>
            <p class="mt-0.5 text-sm text-slate-900">{{ exam.occupational.afp?.name || '—' }}</p>
          </div>
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Ciudad</p>
            <p class="mt-0.5 text-sm text-slate-900">{{ exam.occupational.city?.name }}</p>
            <p class="text-xs text-slate-500">{{ exam.occupational.city?.department }}</p>
          </div>

          <div class="sm:col-span-2 lg:col-span-4">
            <p class="mb-1.5 text-xs font-medium tracking-wide text-slate-500 uppercase">Riesgos del puesto</p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="risk in exam.occupational.risks" :key="risk.id" class="chip">{{ risk.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- -------------------------------------------------- concepto de aptitud -->
      <div class="card mb-5 overflow-hidden">
        <header class="section-heading">
          <h2 class="text-sm font-bold text-slate-900">Concepto de aptitud</h2>
        </header>

        <div class="divide-y divide-slate-100">
          <div
            v-for="aptitude in exam.aptitudes"
            :key="aptitude.key"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
          >
            <span class="text-sm font-medium text-slate-800">{{ aptitude.label }}</span>
            <span
              v-if="aptitude.value"
              :class="[
                'rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset',
                RESULT_STYLES[aptitude.value] || RESULT_STYLES.APTO,
              ]"
            >
              {{ aptitude.value_label }}
            </span>
            <span v-else class="text-xs text-slate-400 italic">No aplica para este puesto</span>
          </div>
        </div>

        <div v-if="exam.exam.restrictions" class="border-t border-slate-200 px-5 py-4">
          <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
            Recomendaciones / restricciones
          </p>
          <p class="mt-1 text-sm text-slate-800">{{ exam.exam.restrictions }}</p>
          <p v-if="exam.exam.restrictions_validity" class="mt-1 text-xs text-slate-500">
            Temporalidad: {{ exam.exam.restrictions_validity }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-5 py-3">
          <span class="text-xs font-medium tracking-wide text-slate-500 uppercase">
            Consentimiento informado
          </span>
          <span
            :class="[
              'rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
              exam.exam.consent_accepted
                ? 'bg-emerald-100 text-emerald-800 ring-emerald-300'
                : 'bg-red-100 text-red-900 ring-red-300',
            ]"
          >
            {{ exam.exam.consent_accepted ? 'Aceptado' : 'No aceptado' }}
          </span>
        </div>
      </div>

      <!-- ---------------------------------------------------------- verificación -->
      <div class="card mb-5 p-5">
        <h2 class="mb-3 text-sm font-bold text-slate-900">Verificación pública (código QR)</h2>
        <div class="flex flex-wrap items-center gap-3">
          <code class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs break-all text-slate-700">
            {{ exam.verification.url }}
          </code>
          <button type="button" class="btn-secondary" @click="copyVerificationUrl">
            {{ copied ? '¡Copiado!' : 'Copiar enlace' }}
          </button>
          <RouterLink
            :to="{ name: 'public.verify', params: { code: exam.verification.code } }"
            class="btn-secondary"
            target="_blank"
          >
            Abrir verificación
          </RouterLink>
        </div>
        <p class="mt-2 text-xs text-slate-500">
          Este enlace es el destino del código QR impreso en el PDF y muestra la leyenda oficial del centro médico.
        </p>
      </div>

      <!-- ------------------------------------------------------------ paraclínicos -->
      <div v-if="exam.paraclinicals?.length" class="card mb-5 overflow-hidden">
        <header class="section-heading">
          <h2 class="text-sm font-bold text-slate-900">Paraclínicos</h2>
        </header>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-left">
                <th class="px-5 py-2.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">Prueba</th>
                <th class="px-5 py-2.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">Concepto</th>
                <th class="px-5 py-2.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">Resultado</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in exam.paraclinicals" :key="item.key">
                <td class="px-5 py-2.5 text-slate-800">{{ item.label }}</td>
                <td class="px-5 py-2.5">
                  <span v-if="!item.performed" class="text-xs text-slate-400 italic">No realizado</span>
                  <span
                    v-else
                    :class="[
                      'rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                      item.status === 'alterada'
                        ? 'bg-amber-100 text-amber-900 ring-amber-300'
                        : 'bg-emerald-100 text-emerald-800 ring-emerald-300',
                    ]"
                  >
                    {{ item.status === 'alterada' ? 'Alterada' : 'Normal' }}
                  </span>
                </td>
                <td class="px-5 py-2.5 text-xs text-slate-600">{{ item.result || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ----------------------------------------------------------- examen físico -->
      <div class="card mb-5 overflow-hidden">
        <header class="section-heading">
          <h2 class="text-sm font-bold text-slate-900">Examen físico</h2>
        </header>

        <div v-if="systemRows.length" class="border-b border-slate-200 p-5">
          <h3 class="mb-2 text-xs font-bold tracking-wide text-brand-800 uppercase">Sistemas revisados</h3>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div
              v-for="row in systemRows"
              :key="row.key"
              class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2"
            >
              <span class="text-xs text-slate-600">{{ row.label }}</span>
              <span
                :class="[
                  'rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                  row.findings
                    ? 'bg-amber-100 text-amber-900 ring-amber-300'
                    : 'bg-emerald-100 text-emerald-800 ring-emerald-300',
                ]"
              >
                {{ row.value }}
              </span>
            </div>
          </div>
        </div>

        <div v-if="exam.exam.clinical_findings" class="border-b border-slate-200 px-5 py-4">
          <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Hallazgos clínicos relevantes</p>
          <p class="mt-1 text-sm text-slate-800">{{ exam.exam.clinical_findings }}</p>
        </div>

        <div class="grid grid-cols-1 gap-5 p-5 md:grid-cols-2">
          <section v-for="group in parameterGroups" :key="group.key">
            <h3 class="mb-2 text-xs font-bold tracking-wide text-brand-800 uppercase">{{ group.title }}</h3>
            <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200">
              <div
                v-for="(value, key) in exam.medical_parameters[group.key]"
                :key="key"
                class="flex items-baseline justify-between gap-4 px-3 py-2"
              >
                <dt class="text-xs text-slate-500">{{ humanize(key) }}</dt>
                <dd class="text-right text-xs font-medium text-slate-900">{{ display(value) }}</dd>
              </div>
            </dl>
          </section>
        </div>
      </div>

      <div class="card p-5">
        <h2 class="mb-2 text-sm font-bold text-slate-900">Recomendaciones</h2>
        <ul class="list-inside list-disc space-y-1 text-sm text-slate-700">
          <li v-for="(item, index) in exam.exam.recommendations" :key="index">{{ item }}</li>
        </ul>
      </div>

      <div
        v-if="annulOpen"
        class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="annul-title"
      >
        <div class="card w-full max-w-md p-5">
          <h2 id="annul-title" class="text-sm font-bold text-slate-900">
            Anular la evaluación {{ exam.order_code }}
          </h2>

          <p class="mt-2 text-sm text-slate-600">
            El registro se conserva: quien escanee el QR ya impreso verá que el documento fue anulado.
          </p>

          <label for="annul-reason" class="field-label mt-4">Motivo de la anulación</label>
          <textarea
            id="annul-reason"
            v-model="annulReason"
            rows="2"
            class="field-input"
            placeholder="Ej. Error en el concepto de aptitud"
          ></textarea>

          <p v-if="annulError" class="field-error">{{ annulError }}</p>

          <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="btn-secondary" @click="annulOpen = false">Cancelar</button>
            <button
              type="button"
              class="btn-primary bg-red-700 hover:bg-red-800 focus-visible:ring-red-500"
              :disabled="annulling"
              @click="confirmAnnulment"
            >
              {{ annulling ? 'Anulando…' : 'Anular evaluación' }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
