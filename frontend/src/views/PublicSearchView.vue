<script setup>
import { ref } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import api, { parseApiError } from '@/services/api'

const documentNumber = ref('')
const loading = ref(false)
const result = ref(null)
const notFound = ref(null)
const error = ref(null)
const searched = ref(false)

async function search() {
  loading.value = true
  result.value = null
  notFound.value = null
  error.value = null

  try {
    const { data } = await api.get('/public/exams/search', {
      params: { document_number: documentNumber.value.trim() },
    })
    result.value = data
  } catch (err) {
    const status = err.response?.status

    if (status === 404) {
      notFound.value = err.response.data.message
    } else {
      error.value = parseApiError(err).message
    }
  } finally {
    loading.value = false
    searched.value = true
  }
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
  <div class="mx-auto max-w-3xl">
    <div class="mb-7 text-center">
      <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Consulta pública de exámenes</h1>
      <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600">
        Verifique si un trabajador cuenta con examen médico ocupacional vigente emitido por nuestro
        centro médico. Ingrese el número de cédula sin puntos ni comas.
      </p>
    </div>

    <form class="card p-5 sm:p-6" novalidate @submit.prevent="search">
      <label for="document" class="field-label">Número de cédula</label>
      <div class="flex flex-col gap-3 sm:flex-row">
        <input
          id="document"
          v-model="documentNumber"
          type="text"
          inputmode="numeric"
          pattern="[0-9]*"
          class="field-input flex-1"
          placeholder="Ej. 1020304050"
          required
        />
        <button type="submit" class="btn-primary sm:w-40" :disabled="loading || !documentNumber.trim()">
          <svg v-if="!loading" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
          </svg>
          {{ loading ? 'Consultando…' : 'Consultar' }}
        </button>
      </div>
    </form>

    <div v-if="searched" class="mt-6 space-y-4">
      <AlertMessage v-if="error" variant="error" title="No se pudo completar la consulta">
        {{ error }}
      </AlertMessage>

      <AlertMessage v-if="notFound" variant="warning" title="Sin registros">
        {{ notFound }}
      </AlertMessage>

      <template v-if="result">
        <AlertMessage variant="success" title="Examen médico confirmado">
          {{ result.message }} Documento emitido por <strong>{{ result.issuer }}</strong>.
        </AlertMessage>

        <article v-for="item in result.results" :key="item.order_code" class="card overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-3">
            <div>
              <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Orden No.</p>
              <p class="font-mono text-sm font-bold text-brand-800">{{ item.order_code }}</p>
            </div>
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-300 ring-inset">
              {{ item.result_label }}
            </span>
          </div>

          <dl class="grid grid-cols-1 gap-x-6 gap-y-3.5 px-5 py-4 sm:grid-cols-2">
            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Trabajador</dt>
              <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ item.full_name }}</dd>
            </div>
            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Cédula</dt>
              <dd class="mt-0.5 font-mono text-sm text-slate-900">{{ item.document_number }}</dd>
            </div>
            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Tipo de examen</dt>
              <dd class="mt-0.5 text-sm text-slate-900">{{ item.exam_type_label }}</dd>
            </div>
            <div>
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Fecha del examen</dt>
              <dd class="mt-0.5 text-sm text-slate-900">{{ formatDate(item.exam_date) }}</dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Fecha exacta de expedición</dt>
              <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ item.issued_at_label }}</dd>
            </div>
          </dl>

          <div class="border-t border-slate-200 bg-slate-50 px-5 py-3">
            <RouterLink
              :to="{ name: 'public.verify', params: { code: item.verification_code } }"
              class="text-sm font-semibold text-brand-700 hover:underline"
            >
              Ver leyenda oficial de verificación →
            </RouterLink>
          </div>
        </article>
      </template>
    </div>

    <p class="mt-8 text-center text-xs text-slate-500">
      Por protección de datos personales solo se muestran las iniciales del trabajador y los últimos
      cuatro dígitos del documento.
    </p>
  </div>
</template>
