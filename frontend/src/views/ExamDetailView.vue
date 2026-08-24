<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { downloadExamPdf, openExamPdf } from '@/services/pdf'

const route = useRoute()

const exam = ref(null)
const loading = ref(true)
const error = ref(null)
const busy = ref(null)
const copied = ref(false)

const justCreated = computed(() => route.query.created === '1')

const parameterGroups = [
  { key: 'signos_vitales', title: 'Signos vitales' },
  { key: 'antropometria', title: 'Antropometría' },
  { key: 'agudeza_visual', title: 'Agudeza visual' },
  { key: 'audiometria', title: 'Audiometría' },
  { key: 'espirometria', title: 'Espirometría' },
  { key: 'laboratorio', title: 'Laboratorio clínico' },
  { key: 'examen_fisico', title: 'Examen físico' },
  { key: 'antecedentes', title: 'Antecedentes' },
  { key: 'concepto_medico', title: 'Concepto médico' },
]

onMounted(async () => {
  try {
    const { data } = await api.get(`/exams/${route.params.id}`)
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

async function copyVerificationUrl() {
  try {
    await navigator.clipboard.writeText(exam.value.verification.url)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    error.value = 'No fue posible copiar el enlace al portapapeles.'
  }
}

// Las claves del JSON vienen sin tildes; se traducen para mostrarlas.
const parameterLabels = {
  presion_arterial: 'Presión arterial',
  presion_sistolica: 'Presión sistólica',
  presion_diastolica: 'Presión diastólica',
  frecuencia_cardiaca: 'Frecuencia cardíaca',
  frecuencia_respiratoria: 'Frecuencia respiratoria',
  saturacion_oxigeno: 'Saturación de oxígeno',
  estatura_cm: 'Estatura (cm)',
  peso_kg: 'Peso (kg)',
  peso_ideal_kg: 'Peso adecuado (kg)',
  rango_peso_saludable_kg: 'Rango saludable (kg)',
  imc: 'IMC',
  clasificacion_imc: 'Clasificación IMC',
  ojo_derecho: 'Ojo derecho',
  ojo_izquierdo: 'Ojo izquierdo',
  vision_binocular: 'Visión binocular',
  vision_cromatica: 'Visión cromática',
  vision_profundidad: 'Visión de profundidad',
  oido_derecho: 'Oído derecho',
  oido_izquierdo: 'Oído izquierdo',
  cvf: 'CVF',
  vef1: 'VEF1',
  relacion_vef1_cvf: 'Relación VEF1/CVF',
  glicemia: 'Glicemia',
  colesterol_total: 'Colesterol total',
  trigliceridos: 'Triglicéridos',
  hemoglobina: 'Hemoglobina',
  cabeza_cuello: 'Cabeza y cuello',
  piel_faneras: 'Piel y faneras',
  neurologico: 'Neurológico',
  diagnostico: 'Diagnóstico',
  alergicos: 'Alérgicos',
  quirurgicos: 'Quirúrgicos',
}

function humanize(key) {
  if (parameterLabels[key]) return parameterLabels[key]

  const text = key.replace(/_/g, ' ')
  return text.charAt(0).toUpperCase() + text.slice(1)
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
    <LoadingSpinner v-if="loading" label="Cargando examen…" />

    <AlertMessage v-else-if="error && !exam" variant="error">{{ error }}</AlertMessage>

    <template v-else-if="exam">
      <RouterLink :to="{ name: 'admin.exams' }" class="mb-4 inline-block text-sm font-medium text-brand-700 hover:underline">
        ← Volver al listado
      </RouterLink>

      <AlertMessage v-if="justCreated" variant="success" title="Examen generado correctamente" class="mb-5">
        Se asignó el consecutivo <strong>{{ exam.order_code }}</strong> y el documento ya puede descargarse en PDF
        con su código QR de verificación.
      </AlertMessage>

      <AlertMessage v-if="error" variant="error" class="mb-5">{{ error }}</AlertMessage>

      <div class="card mb-5 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4">
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
              Orden No. (consecutivo {{ exam.order_number }})
            </p>
            <p class="font-mono text-lg font-bold text-brand-800">{{ exam.order_code }}</p>
          </div>

          <div class="flex flex-wrap gap-2">
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
              C.C. {{ exam.worker.document_number }} · {{ exam.worker.age }} años
            </p>
          </div>
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Concepto</p>
            <p class="mt-1">
              <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-800 ring-1 ring-emerald-300 ring-inset">
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
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Contacto</p>
            <p class="mt-0.5 text-sm text-slate-900">{{ exam.worker.email }}</p>
            <p class="text-xs text-slate-500">{{ exam.worker.phone }}</p>
          </div>
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Estatura / peso adecuado</p>
            <p class="mt-0.5 text-sm text-slate-900">
              {{ exam.worker.height_cm }} cm · {{ exam.worker.ideal_weight_kg }} kg
            </p>
            <p class="text-xs text-slate-500">Registrado: {{ exam.worker.weight_kg }} kg</p>
          </div>
          <div class="lg:col-span-2">
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Empresa</p>
            <p class="mt-0.5 text-sm text-slate-900">
              {{ exam.occupational.company_name }} — NIT {{ exam.occupational.company_nit }}
            </p>
            <p class="text-xs text-slate-500">Cargo: {{ exam.occupational.position }}</p>
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
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Ciudad</p>
            <p class="mt-0.5 text-sm text-slate-900">{{ exam.occupational.city?.name }}</p>
          </div>
          <div>
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Expedido</p>
            <p class="mt-0.5 text-sm text-slate-900">
              {{ new Date(exam.issued_at).toLocaleString('es-CO') }}
            </p>
          </div>

          <div class="sm:col-span-2 lg:col-span-4">
            <p class="mb-1.5 text-xs font-medium tracking-wide text-slate-500 uppercase">Riesgos / especialidades</p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="risk in exam.occupational.risks" :key="risk.id" class="chip">{{ risk.name }}</span>
            </div>
          </div>
        </div>
      </div>

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

      <div class="card mb-5 overflow-hidden">
        <header class="section-heading">
          <h2 class="text-sm font-bold text-slate-900">Parámetros médicos diligenciados automáticamente</h2>
        </header>

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
                <dd class="text-right text-xs font-medium text-slate-900">{{ value }}</dd>
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
    </template>
  </div>
</template>
