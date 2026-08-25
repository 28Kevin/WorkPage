<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'

const route = useRoute()
const loading = ref(true)
const data = ref(null)
const error = ref(null)
// Un documento anulado responde 410: existe, pero perdio validez.
const annulled = ref(false)

onMounted(async () => {
  try {
    const response = await api.get(`/public/verify/${encodeURIComponent(route.params.code)}`)
    data.value = response.data
  } catch (err) {
    const status = err.response?.status

    annulled.value = status === 410
    error.value = [404, 410].includes(status)
      ? err.response.data.message
      : parseApiError(err).message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <LoadingSpinner v-if="loading" label="Verificando documento…" />

    <AlertMessage
      v-else-if="error"
      variant="error"
      :title="annulled ? 'Documento anulado' : 'Documento no verificado'"
    >
      {{ error }}
    </AlertMessage>

    <template v-else-if="data">
      <div class="card overflow-hidden">
        <div class="flex items-center gap-3 bg-emerald-600 px-6 py-5 text-white">
          <svg class="h-9 w-9 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z" />
            <path d="m9 12 2 2 4-4" />
          </svg>
          <div>
            <p class="text-xs font-semibold tracking-wider uppercase opacity-90">Verificación oficial</p>
            <h1 class="text-xl font-bold">Documento auténtico</h1>
          </div>
        </div>

        <div class="border-b border-slate-200 bg-emerald-50 px-6 py-4">
          <p class="text-sm leading-relaxed text-emerald-900">{{ data.legend }}</p>
        </div>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Orden No.</dt>
            <dd class="mt-0.5 font-mono text-sm font-bold text-brand-800">{{ data.exam.order_code }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Concepto</dt>
            <dd class="mt-0.5">
              <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 ring-1 ring-emerald-300 ring-inset">
                {{ data.exam.result_label }}
              </span>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Trabajador</dt>
            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ data.exam.full_name }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Cédula</dt>
            <dd class="mt-0.5 font-mono text-sm text-slate-900">{{ data.exam.document_number }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Tipo de examen</dt>
            <dd class="mt-0.5 text-sm text-slate-900">{{ data.exam.exam_type_label }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Ciudad</dt>
            <dd class="mt-0.5 text-sm text-slate-900">{{ data.exam.city }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">ARL</dt>
            <dd class="mt-0.5 text-sm text-slate-900">{{ data.exam.arl }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Fecha de expedición</dt>
            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ data.exam.issued_at_label }}</dd>
          </div>
        </dl>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 text-xs leading-relaxed text-slate-600">
          <p class="font-semibold text-slate-800">{{ data.issuer.name }}</p>
          <p>NIT {{ data.issuer.nit }}</p>
          <p>Tel. {{ data.issuer.phone }} · {{ data.issuer.email }}</p>
        </div>
      </div>

      <p class="mt-5 text-center text-sm">
        <RouterLink :to="{ name: 'public.search' }" class="font-semibold text-brand-700 hover:underline">
          Consultar otro documento por cédula
        </RouterLink>
      </p>
    </template>
  </div>
</template>
