<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import PerPageSelect from '@/components/PerPageSelect.vue'
import SortableHeader from '@/components/SortableHeader.vue'
import { useTableQuery } from '@/composables/useTableQuery'
import api, { parseApiError } from '@/services/api'
import { downloadFoodHandlerPdf } from '@/services/pdf'

const records = ref([])
const meta = ref(null)
const search = ref('')
const status = ref('active')
const page = ref(1)
const loading = ref(true)
const error = ref(null)
const notice = ref(null)
const downloading = ref(null)

/** Orden y tamaño de página del listado. */
const { sort, direction, perPage, toggleSort, params } = useTableQuery('order_code')


// Anulación: se pide un motivo antes de confirmar.
const annulling = ref(null)
const annulReason = ref('')
const annulError = ref(null)
const savingAnnulment = ref(false)

let searchTimer = null

async function fetchRecords() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get('/food-handler', {
      params: {
        search: search.value.trim() || undefined,
        status: status.value,
        page: page.value,
        ...params.value,
      },
    })

    records.value = data.data
    meta.value = data.meta
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
}

onMounted(fetchRecords)

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchRecords()
  }, 350)
})

watch(status, () => {
  page.value = 1
  fetchRecords()
})

watch(page, fetchRecords)

// Al cambiar el orden o el tamaño se vuelve a la primera página.
watch([sort, direction, perPage], () => {
  if (page.value === 1) {
    fetchRecords()

    return
  }

  page.value = 1
})

async function download(record) {
  downloading.value = record.id

  try {
    await downloadFoodHandlerPdf(record.id)
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    downloading.value = null
  }
}

function askAnnulment(record) {
  annulling.value = record
  annulReason.value = ''
  annulError.value = null
}

async function confirmAnnulment() {
  savingAnnulment.value = true
  annulError.value = null

  try {
    const { data } = await api.delete(`/food-handler/${annulling.value.id}`, {
      data: { reason: annulReason.value.trim() },
    })

    notice.value = data.message
    annulling.value = null
    await fetchRecords()
  } catch (err) {
    const parsed = parseApiError(err)
    annulError.value = parsed.errors?.reason?.[0] || parsed.message
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
        <h1 class="text-2xl font-bold text-slate-900">Manipulación de alimentos</h1>
        <p class="mt-1 text-sm text-slate-600">
          {{ meta?.total ?? 0 }} paquete(s) emitido(s) con numeración consecutiva propia.
        </p>
      </div>

      <RouterLink :to="{ name: 'admin.food.create' }" class="btn-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" aria-hidden="true">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Nuevo paquete
      </RouterLink>
    </div>

    <div class="card mb-5 flex flex-wrap items-center gap-3 p-4">
      <label for="food-search" class="sr-only">Buscar</label>
      <input
        id="food-search"
        v-model="search"
        type="search"
        class="field-input flex-1"
        placeholder="Buscar por nombre, cédula o número de documento…"
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

    <LoadingSpinner v-if="loading" label="Cargando paquetes…" />

    <div v-else-if="!records.length" class="card px-6 py-14 text-center">
      <p class="text-sm font-medium text-slate-900">
        {{ status === 'annulled' ? 'No hay paquetes anulados' : 'No hay paquetes registrados' }}
      </p>
      <p class="mt-1 text-sm text-slate-500">
        {{ search ? 'Ajuste los términos de búsqueda.' : 'Genere el primero desde el formulario.' }}
      </p>
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left">
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <SortableHeader field="order_code" label="Documento" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="full_name" label="Paciente" first="asc" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="document_number" label="Cédula" first="asc" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="issue_date" label="Fecha" :sort="sort" :direction="direction" @sort="toggleSort" />
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="record in records" :key="record.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-xs font-bold text-brand-900">
              {{ record.order_code }}
              <span v-if="record.annulled" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-800">
                ANULADO
              </span>
            </td>
            <td class="px-4 py-3 font-medium text-slate-900">
              {{ record.full_name }}
              <span class="block text-xs font-normal text-slate-500">{{ record.age }} años</span>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ record.document_number }}</td>
            <td class="px-4 py-3 text-slate-600">{{ formatDate(record.issue_date) }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap items-center justify-end gap-2">
                <RouterLink
                  :to="{ name: 'admin.food.show', params: { id: record.id } }"
                  class="btn-ghost text-xs"
                >
                  Ver
                </RouterLink>

                <button
                  type="button"
                  class="btn-ghost text-xs"
                  :disabled="downloading === record.id"
                  @click="download(record)"
                >
                  {{ downloading === record.id ? 'Generando…' : 'PDF' }}
                </button>

                <button
                  v-if="!record.annulled"
                  type="button"
                  class="btn-ghost text-xs text-red-700"
                  @click="askAnnulment(record)"
                >
                  Anular
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta" class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
      <PerPageSelect v-model="perPage" :total="meta.total" />

      <div v-if="meta.last_page > 1" class="flex items-center gap-3">
        <button type="button" class="btn-secondary" :disabled="page <= 1" @click="page -= 1">
          Anterior
        </button>
        <span class="text-slate-600">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button type="button" class="btn-secondary" :disabled="page >= meta.last_page" @click="page += 1">
          Siguiente
        </button>
      </div>
    </div>

    <!-- Anulación: el documento no se borra, el QR impreso sigue respondiendo. -->
    <div v-if="annulling" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <div class="card w-full max-w-md p-5">
        <h2 class="text-base font-bold text-slate-900">Anular {{ annulling.order_code }}</h2>
        <p class="mt-1 text-sm text-slate-600">
          El documento no se elimina: el código QR del carné seguirá respondiendo, indicando que
          perdió validez.
        </p>

        <label for="annul-reason" class="field-label mt-4">Motivo de la anulación</label>
        <textarea
          id="annul-reason"
          v-model="annulReason"
          rows="3"
          class="field-input"
          placeholder="Explique por qué se anula este documento…"
        ></textarea>

        <p v-if="annulError" class="field-error">{{ annulError }}</p>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="annulling = null">Cancelar</button>
          <button
            type="button"
            class="btn-primary bg-red-600 hover:bg-red-700"
            :disabled="savingAnnulment"
            @click="confirmAnnulment"
          >
            {{ savingAnnulment ? 'Anulando…' : 'Anular documento' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
