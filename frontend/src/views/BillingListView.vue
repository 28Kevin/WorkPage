<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import PerPageSelect from '@/components/PerPageSelect.vue'
import SortableHeader from '@/components/SortableHeader.vue'
import { useTableQuery } from '@/composables/useTableQuery'
import api, { parseApiError } from '@/services/api'
import { downloadBillingPdf } from '@/services/pdf'

const route = useRoute()

const accounts = ref([])
const meta = ref(null)
const search = ref('')
const status = ref('active')
const page = ref(1)
const loading = ref(true)
const error = ref(null)
const notice = ref(null)
const downloading = ref(null)

/** Orden y tamaño de página del listado. */
const { sort, direction, perPage, toggleSort, params } = useTableQuery('number_code')


const annulling = ref(null)
const annulReason = ref('')
const annulError = ref(null)
const savingAnnulment = ref(false)

let searchTimer = null

async function fetchAccounts() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get('/billing', {
      params: {
        search: search.value.trim() || undefined,
        status: status.value,
        page: page.value,
        ...params.value,
      },
    })

    accounts.value = data.data
    meta.value = data.meta
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (route.query.saved) {
    notice.value = `Cuenta de cobro ${route.query.saved} guardada correctamente.`
  }

  fetchAccounts()
})

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchAccounts()
  }, 350)
})

watch(status, () => {
  page.value = 1
  fetchAccounts()
})

watch(page, fetchAccounts)

// Al cambiar el orden o el tamaño se vuelve a la primera página.
watch([sort, direction, perPage], () => {
  if (page.value === 1) {
    fetchAccounts()

    return
  }

  page.value = 1
})

async function download(account) {
  downloading.value = account.id

  try {
    await downloadBillingPdf(account.id)
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    downloading.value = null
  }
}

function askAnnulment(account) {
  annulling.value = account
  annulReason.value = ''
  annulError.value = null
}

async function confirmAnnulment() {
  savingAnnulment.value = true
  annulError.value = null

  try {
    const { data } = await api.delete(`/billing/${annulling.value.id}`, {
      data: { reason: annulReason.value.trim() },
    })

    notice.value = data.message
    annulling.value = null
    await fetchAccounts()
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
        <h1 class="text-2xl font-bold text-slate-900">Cuentas de cobro</h1>
        <p class="mt-1 text-sm text-slate-600">
          {{ meta?.total ?? 0 }} cuenta(s) generada(s), con consecutivo de cinco dígitos.
        </p>
      </div>

      <RouterLink :to="{ name: 'admin.billing.create' }" class="btn-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" aria-hidden="true">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Nueva cuenta de cobro
      </RouterLink>
    </div>

    <div class="card mb-5 flex flex-wrap items-center gap-3 p-4">
      <label for="billing-search" class="sr-only">Buscar</label>
      <input
        id="billing-search"
        v-model="search"
        type="search"
        class="field-input flex-1"
        placeholder="Buscar por empresa, NIT o número de cuenta…"
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
          Anuladas
        </button>
      </div>
    </div>

    <AlertMessage v-if="notice" variant="success" class="mb-4">{{ notice }}</AlertMessage>
    <AlertMessage v-if="error" variant="error" class="mb-4">{{ error }}</AlertMessage>

    <LoadingSpinner v-if="loading" label="Cargando cuentas…" />

    <div v-else-if="!accounts.length" class="card px-6 py-14 text-center">
      <p class="text-sm font-medium text-slate-900">
        {{ status === 'annulled' ? 'No hay cuentas anuladas' : 'No hay cuentas generadas' }}
      </p>
      <p class="mt-1 text-sm text-slate-500">
        {{ search ? 'Ajuste los términos de búsqueda.' : 'Genere la primera desde el formulario.' }}
      </p>
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left">
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <SortableHeader field="number_code" label="No." :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="company_name" label="Empresa" first="asc" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="issue_date" label="Expedición" :sort="sort" :direction="direction" @sort="toggleSort" />
            <SortableHeader field="total" label="Total" align="right" :sort="sort" :direction="direction" @sort="toggleSort" />
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="account in accounts" :key="account.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-xs font-bold text-brand-900">
              {{ account.number_code }}
              <span v-if="account.annulment.annulled" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-800">
                ANULADA
              </span>
            </td>
            <td class="px-4 py-3 font-medium text-slate-900">
              {{ account.company.name }}
              <span class="block text-xs font-normal text-slate-500">NIT {{ account.company.nit }}</span>
            </td>
            <td class="px-4 py-3 text-slate-600">
              {{ formatDate(account.issue_date) }}
              <span class="block text-xs text-slate-500">{{ account.issue_place }}</span>
            </td>
            <td class="px-4 py-3 text-right font-mono font-semibold text-slate-900">
              {{ account.total_label }}
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap items-center justify-end gap-2">
                <RouterLink
                  v-if="!account.annulment.annulled"
                  :to="{ name: 'admin.billing.edit', params: { id: account.id } }"
                  class="btn-ghost text-xs"
                >
                  Editar
                </RouterLink>

                <button
                  type="button"
                  class="btn-ghost text-xs"
                  :disabled="downloading === account.id"
                  @click="download(account)"
                >
                  {{ downloading === account.id ? 'Generando…' : 'PDF' }}
                </button>

                <button
                  v-if="!account.annulment.annulled"
                  type="button"
                  class="btn-ghost text-xs text-red-700"
                  @click="askAnnulment(account)"
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
        <h2 class="text-base font-bold text-slate-900">Anular cuenta {{ annulling.number_code }}</h2>
        <p class="mt-1 text-sm text-slate-600">
          La cuenta no se elimina y su consecutivo no se reutiliza.
        </p>

        <label for="billing-annul-reason" class="field-label mt-4">Motivo de la anulación</label>
        <textarea
          id="billing-annul-reason"
          v-model="annulReason"
          rows="3"
          class="field-input"
          placeholder="Explique por qué se anula esta cuenta…"
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
            {{ savingAnnulment ? 'Anulando…' : 'Anular cuenta' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
