<script setup>
import { onMounted, ref, watch } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { downloadExamPdf } from '@/services/pdf'

const exams = ref([])
const meta = ref(null)
const search = ref('')
const status = ref('active')
const page = ref(1)
const loading = ref(true)
const error = ref(null)
const notice = ref(null)
const downloading = ref(null)

// Anulación: se pide un motivo antes de confirmar.
const annulling = ref(null)
const annulReason = ref('')
const annulError = ref(null)
const savingAnnulment = ref(false)

/** Colores del concepto de aptitud según su severidad. */
const RESULT_STYLES = {
  APTO: 'bg-emerald-100 text-emerald-800 ring-emerald-300',
  APTO_CON_RESTRICCIONES: 'bg-amber-100 text-amber-900 ring-amber-300',
  APLAZADO: 'bg-orange-100 text-orange-900 ring-orange-300',
  NO_APTO: 'bg-red-100 text-red-900 ring-red-300',
}

let searchTimer = null

async function fetchExams() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get('/exams', {
      params: {
        search: search.value.trim() || undefined,
        status: status.value,
        page: page.value,
      },
    })

    exams.value = data.data
    meta.value = data.meta
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
}

onMounted(fetchExams)

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchExams()
  }, 350)
})

watch(status, () => {
  page.value = 1
  fetchExams()
})

watch(page, fetchExams)

async function download(exam) {
  downloading.value = exam.id

  try {
    await downloadExamPdf(exam.id)
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    downloading.value = null
  }
}

function askAnnulment(exam) {
  annulling.value = exam
  annulReason.value = ''
  annulError.value = null
}

async function confirmAnnulment() {
  savingAnnulment.value = true
  annulError.value = null

  try {
    const { data } = await api.delete(`/exams/${annulling.value.id}`, {
      data: { reason: annulReason.value.trim() },
    })

    notice.value = data.message
    annulling.value = null
    await fetchExams()
  } catch (err) {
    annulError.value = parseApiError(err).errors?.reason?.[0] || parseApiError(err).message
  } finally {
    savingAnnulment.value = false
  }
}

function formatDate(value) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('es-CO')
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Evaluaciones emitidas</h1>
        <p class="mt-1 text-sm text-slate-600">
          {{ meta?.total ?? 0 }} documento(s) con numeración consecutiva.
        </p>
      </div>

      <RouterLink :to="{ name: 'admin.exams.create' }" class="btn-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" aria-hidden="true">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Nueva evaluación
      </RouterLink>
    </div>

    <div class="card mb-5 flex flex-wrap items-center gap-3 p-4">
      <label for="search" class="sr-only">Buscar</label>
      <input
        id="search"
        v-model="search"
        type="search"
        class="field-input flex-1"
        placeholder="Buscar por nombre, cédula, empresa o número de orden…"
      >

      <div class="flex gap-1 rounded-lg border border-slate-200 p-1">
        <button
          type="button"
          class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
          :class="status === 'active' ? 'bg-brand-50 text-brand-800' : 'text-slate-500 hover:bg-slate-100'"
          @click="status = 'active'"
        >
          Vigentes
        </button>
        <button
          type="button"
          class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
          :class="status === 'annulled' ? 'bg-red-50 text-red-800' : 'text-slate-500 hover:bg-slate-100'"
          @click="status = 'annulled'"
        >
          Anulados
        </button>
      </div>
    </div>

    <AlertMessage v-if="notice" variant="success" class="mb-4">{{ notice }}</AlertMessage>
    <AlertMessage v-if="error" variant="error" class="mb-4">{{ error }}</AlertMessage>

    <LoadingSpinner v-if="loading" label="Cargando evaluaciones…" />

    <div v-else-if="!exams.length" class="card px-6 py-14 text-center">
      <p class="text-sm font-medium text-slate-900">
        {{ status === 'annulled' ? 'No hay evaluaciones anuladas' : 'No hay evaluaciones registradas' }}
      </p>
      <p class="mt-1 text-sm text-slate-500">
        {{ search ? 'Ajuste los términos de búsqueda.' : 'Genere el primer documento desde el formulario.' }}
      </p>
    </div>

    <div v-else class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
            <tr>
              <th scope="col" class="px-4 py-3 font-semibold">Orden</th>
              <th scope="col" class="px-4 py-3 font-semibold">Trabajador</th>
              <th scope="col" class="px-4 py-3 font-semibold">Empleador</th>
              <th scope="col" class="px-4 py-3 font-semibold">Tipo</th>
              <th scope="col" class="px-4 py-3 font-semibold">Fecha</th>
              <th scope="col" class="px-4 py-3 font-semibold">Concepto</th>
              <th scope="col" class="px-4 py-3 text-right font-semibold">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="exam in exams"
              :key="exam.id"
              class="transition hover:bg-slate-50"
              :class="exam.annulled ? 'opacity-60' : ''"
            >
              <td class="px-4 py-3 font-mono text-xs font-semibold whitespace-nowrap text-brand-800">
                {{ exam.order_code }}
                <span v-if="exam.annulled" class="ml-1 font-sans text-[10px] font-bold text-red-700">ANULADO</span>
              </td>
              <td class="px-4 py-3">
                <p class="font-medium text-slate-900">{{ exam.full_name }}</p>
                <p class="font-mono text-xs text-slate-500">{{ exam.document_number }}</p>
              </td>
              <td class="px-4 py-3">
                <p class="text-slate-700">{{ exam.company_name }}</p>
                <p class="text-xs text-slate-500">{{ exam.position }}</p>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ exam.exam_type_label }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ formatDate(exam.exam_date) }}</td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 ring-inset',
                    RESULT_STYLES[exam.result] || RESULT_STYLES.APTO,
                  ]"
                >
                  {{ exam.result_label }}
                </span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <RouterLink
                  :to="{ name: 'admin.exams.show', params: { id: exam.id } }"
                  class="rounded px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-50"
                >
                  Ver
                </RouterLink>

                <RouterLink
                  v-if="!exam.annulled"
                  :to="{ name: 'admin.exams.edit', params: { id: exam.id } }"
                  class="rounded px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                >
                  Editar
                </RouterLink>

                <button
                  type="button"
                  class="rounded px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                  :disabled="downloading === exam.id"
                  @click="download(exam)"
                >
                  {{ downloading === exam.id ? 'Descargando…' : 'PDF' }}
                </button>

                <button
                  v-if="!exam.annulled"
                  type="button"
                  class="rounded px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50"
                  @click="askAnnulment(exam)"
                >
                  Anular
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta && meta.last_page > 1"
           class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
        <span class="text-slate-600">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <div class="flex gap-2">
          <button type="button" class="btn-secondary py-1.5 text-xs" :disabled="page <= 1" @click="page--">
            Anterior
          </button>
          <button type="button" class="btn-secondary py-1.5 text-xs" :disabled="page >= meta.last_page" @click="page++">
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <!-- ------------------------------------------------------ anular evaluación -->
    <div
      v-if="annulling"
      class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="annul-title"
    >
      <div class="card w-full max-w-md p-5">
        <h2 id="annul-title" class="text-sm font-bold text-slate-900">
          Anular la evaluación {{ annulling.order_code }}
        </h2>

        <p class="mt-2 text-sm text-slate-600">
          El documento deja de aparecer en el listado y en la consulta pública, pero el registro se conserva:
          quien escanee el QR ya impreso verá que fue anulado.
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
          <button type="button" class="btn-secondary" @click="annulling = null">Cancelar</button>
          <button
            type="button"
            class="btn-primary bg-red-700 hover:bg-red-800 focus-visible:ring-red-500"
            :disabled="savingAnnulment"
            @click="confirmAnnulment"
          >
            {{ savingAnnulment ? 'Anulando…' : 'Anular evaluación' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
