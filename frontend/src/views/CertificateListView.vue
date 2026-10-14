<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import PerPageSelect from '@/components/PerPageSelect.vue'
import SortableHeader from '@/components/SortableHeader.vue'
import { useTableQuery } from '@/composables/useTableQuery'
import api, { parseApiError } from '@/services/api'
import { formatDateTime } from '@/services/dates'
import { downloadCertificatePdf } from '@/services/pdf'

const route = useRoute()

const certificates = ref([])
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


const annulling = ref(null)
const annulReason = ref('')
const annulError = ref(null)
const savingAnnulment = ref(false)

let searchTimer = null

async function fetchCertificates() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get('/certificates', {
      params: {
        search: search.value.trim() || undefined,
        status: status.value,
        page: page.value,
        ...params.value,
      },
    })

    certificates.value = data.data
    meta.value = data.meta
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (route.query.saved) {
    notice.value = `Certificado ${route.query.saved} guardado correctamente.`
  }

  fetchCertificates()
})

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchCertificates()
  }, 350)
})

watch(status, () => {
  page.value = 1
  fetchCertificates()
})

watch(page, fetchCertificates)

// Al cambiar el orden o el tamaño se vuelve a la primera página.
watch([sort, direction, perPage], () => {
  if (page.value === 1) {
    fetchCertificates()

    return
  }

  page.value = 1
})

async function download(certificate) {
  downloading.value = certificate.id

  try {
    await downloadCertificatePdf(certificate.id)
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    downloading.value = null
  }
}

function askAnnulment(certificate) {
  annulling.value = certificate
  annulReason.value = ''
  annulError.value = null
}

async function confirmAnnulment() {
  savingAnnulment.value = true
  annulError.value = null

  try {
    const { data } = await api.delete(`/certificates/${annulling.value.id}`, {
      data: { reason: annulReason.value.trim() },
    })

    notice.value = data.message
    annulling.value = null
    await fetchCertificates()
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
        <h1 class="text-2xl font-bold text-slate-900">Certificados de capacitación</h1>
        <p class="mt-1 text-sm text-slate-600">
          {{ meta?.total ?? 0 }} certificado(s) emitido(s).
        </p>
      </div>

      <RouterLink :to="{ name: 'admin.certificates.create' }" class="btn-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" aria-hidden="true">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Nuevo certificado
      </RouterLink>
    </div>

    <div class="card mb-5 flex flex-wrap items-center gap-3 p-4">
      <label for="cert-search" class="sr-only">Buscar</label>
      <input
        id="cert-search"
        v-model="search"
        type="search"
        class="field-input flex-1"
        placeholder="Buscar por nombre, documento, curso o consecutivo…"
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

    <LoadingSpinner v-if="loading" label="Cargando certificados…" />

    <div v-else-if="!certificates.length" class="card px-6 py-14 text-center">
      <p class="text-sm font-medium text-slate-900">
        {{ status === 'annulled' ? 'No hay certificados anulados' : 'No hay certificados emitidos' }}
      </p>
      <p class="mt-1 text-sm text-slate-500">
        {{ search ? 'Ajuste los términos de búsqueda.' : 'Genere el primero desde el formulario.' }}
      </p>
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left">
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <SortableHeader field="order_code" label="Consecutivo" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="participant_name" label="Participante" first="asc" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="course" label="Curso" first="asc" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="hours" label="Horas" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="end_date" label="Finalización" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="created_at" label="Registrado" :sort="sort" :direction="direction" @sort="toggleSort" />
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="certificate in certificates" :key="certificate.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-xs font-bold text-brand-900">
              {{ certificate.order_code }}
              <span v-if="certificate.annulled" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-800">
                ANULADO
              </span>
            </td>
            <td class="px-4 py-3 font-medium text-slate-900">
              {{ certificate.participant_name }}
              <span class="block text-xs font-normal text-slate-500">{{ certificate.document_number }}</span>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ certificate.course }}</td>
            <td class="px-4 py-3 text-slate-600">{{ certificate.hours }}</td>
            <td class="px-4 py-3 text-slate-600">{{ formatDate(certificate.end_date) }}</td>
            <td class="px-4 py-3 text-xs whitespace-nowrap text-slate-500">{{ formatDateTime(certificate.created_at) }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap items-center justify-end gap-2">
                <RouterLink
                  v-if="!certificate.annulled"
                  :to="{ name: 'admin.certificates.edit', params: { id: certificate.id } }"
                  class="btn-ghost text-xs"
                >
                  Editar
                </RouterLink>

                <button
                  type="button"
                  class="btn-ghost text-xs"
                  :disabled="downloading === certificate.id"
                  @click="download(certificate)"
                >
                  {{ downloading === certificate.id ? 'Generando…' : 'PDF' }}
                </button>

                <button
                  v-if="!certificate.annulled"
                  type="button"
                  class="btn-ghost text-xs text-red-700"
                  @click="askAnnulment(certificate)"
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
        <button type="button" class="btn-secondary" :disabled="page <= 1" @click="page -= 1">Anterior</button>
        <span class="text-slate-600">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button type="button" class="btn-secondary" :disabled="page >= meta.last_page" @click="page += 1">Siguiente</button>
      </div>
    </div>

    <div v-if="annulling" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <div class="card w-full max-w-md p-5">
        <h2 class="text-base font-bold text-slate-900">Anular {{ annulling.order_code }}</h2>
        <p class="mt-1 text-sm text-slate-600">
          El certificado no se elimina: el código QR seguirá respondiendo, indicando que perdió validez.
        </p>

        <label for="cert-annul-reason" class="field-label mt-4">Motivo de la anulación</label>
        <textarea
          id="cert-annul-reason"
          v-model="annulReason"
          rows="3"
          class="field-input"
          placeholder="Explique por qué se anula este certificado…"
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
            {{ savingAnnulment ? 'Anulando…' : 'Anular certificado' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
