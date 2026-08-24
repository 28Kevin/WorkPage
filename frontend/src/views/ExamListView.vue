<script setup>
import { onMounted, ref, watch } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { downloadExamPdf } from '@/services/pdf'

const exams = ref([])
const meta = ref(null)
const search = ref('')
const page = ref(1)
const loading = ref(true)
const error = ref(null)
const downloading = ref(null)

let searchTimer = null

async function fetchExams() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get('/exams', {
      params: { search: search.value.trim() || undefined, page: page.value },
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

function formatDate(value) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('es-CO')
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Exámenes emitidos</h1>
        <p class="mt-1 text-sm text-slate-600">
          {{ meta?.total ?? 0 }} documento(s) generados con numeración consecutiva.
        </p>
      </div>

      <RouterLink :to="{ name: 'admin.exams.create' }" class="btn-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" aria-hidden="true">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Nuevo examen
      </RouterLink>
    </div>

    <div class="card mb-5 p-4">
      <label for="search" class="sr-only">Buscar</label>
      <input
        id="search"
        v-model="search"
        type="search"
        class="field-input"
        placeholder="Buscar por nombre, cédula, empresa o número de orden…"
      />
    </div>

    <AlertMessage v-if="error" variant="error" class="mb-4">{{ error }}</AlertMessage>

    <LoadingSpinner v-if="loading" label="Cargando exámenes…" />

    <div v-else-if="!exams.length" class="card px-6 py-14 text-center">
      <p class="text-sm font-medium text-slate-900">No hay exámenes registrados</p>
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
              <th scope="col" class="px-4 py-3 font-semibold">Empresa</th>
              <th scope="col" class="px-4 py-3 font-semibold">Tipo</th>
              <th scope="col" class="px-4 py-3 font-semibold">Fecha</th>
              <th scope="col" class="px-4 py-3 font-semibold">Concepto</th>
              <th scope="col" class="px-4 py-3 text-right font-semibold">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="exam in exams" :key="exam.id" class="transition hover:bg-slate-50">
              <td class="px-4 py-3 font-mono text-xs font-semibold whitespace-nowrap text-brand-800">
                {{ exam.order_code }}
              </td>
              <td class="px-4 py-3">
                <p class="font-medium text-slate-900">{{ exam.full_name }}</p>
                <p class="font-mono text-xs text-slate-500">C.C. {{ exam.document_number }}</p>
              </td>
              <td class="px-4 py-3">
                <p class="text-slate-700">{{ exam.company_name }}</p>
                <p class="text-xs text-slate-500">{{ exam.position }}</p>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ exam.exam_type_label }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ formatDate(exam.exam_date) }}</td>
              <td class="px-4 py-3">
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 ring-1 ring-emerald-300 ring-inset">
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
                <button
                  type="button"
                  class="rounded px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                  :disabled="downloading === exam.id"
                  @click="download(exam)"
                >
                  {{ downloading === exam.id ? 'Descargando…' : 'PDF' }}
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
  </div>
</template>
