<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api from '@/services/api'

const route = useRoute()

const data = ref(null)
const invalid = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get(`/public/certificates/${route.params.code}`)
    data.value = response.data
  } catch (error) {
    // 404 y 410 traen su propio mensaje: un código inexistente o uno anulado.
    invalid.value = error.response?.data ?? {
      message: 'No fue posible verificar el certificado en este momento.',
    }
  } finally {
    loading.value = false
  }
})

function formatDate(value) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('es-CO', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-10">
    <LoadingSpinner v-if="loading" label="Verificando certificado…" />

    <!-- Código inexistente o certificado anulado. -->
    <div v-else-if="invalid" class="card overflow-hidden">
      <div class="bg-red-50 px-6 py-5 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
          <svg class="h-6 w-6 text-red-700" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        </div>
        <h1 class="mt-3 text-lg font-bold text-red-900">
          {{ invalid.annulled ? 'Certificado sin validez' : 'Certificado no encontrado' }}
        </h1>
        <p class="mt-1 text-sm text-red-800">{{ invalid.message }}</p>
      </div>
    </div>

    <div v-else-if="data" class="space-y-5">
      <!-- Empresa que respalda la certificación: logo y NIT del formulario. -->
      <div class="card flex flex-wrap items-center gap-5 p-6">
        <div
          v-if="data.company.logo"
          class="flex h-20 w-32 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white"
        >
          <img :src="data.company.logo" alt="Logo de la empresa" class="max-h-full max-w-full object-contain">
        </div>

        <div class="min-w-0">
          <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
            {{ data.document_type_label }}
          </p>
          <p class="font-mono text-xl font-bold text-brand-900">{{ data.certificate.order_code }}</p>
          <p v-if="data.company.nit" class="mt-0.5 text-sm text-slate-600">
            NIT {{ data.company.nit }}
          </p>
        </div>
      </div>

      <!-- Confirmación. -->
      <div class="card overflow-hidden">
        <div class="bg-emerald-50 px-6 py-5">
          <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100">
              <svg class="h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m20 6-11 11-5-5" />
              </svg>
            </div>
            <div>
              <h1 class="text-lg font-bold text-emerald-900">Certificación verificada</h1>
              <p class="mt-1 text-sm leading-relaxed text-emerald-900">{{ data.legend }}</p>
            </div>
          </div>
        </div>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Participante</dt>
            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ data.certificate.participant_name }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Documento</dt>
            <dd class="mt-0.5 font-mono text-sm text-slate-900">{{ data.certificate.document_number }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Formación</dt>
            <dd class="mt-0.5 text-sm text-slate-900">{{ data.certificate.course }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Intensidad</dt>
            <dd class="mt-0.5 text-sm text-slate-900">{{ data.certificate.hours }} horas</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Realizado en</dt>
            <dd class="mt-0.5 text-sm text-slate-900">{{ data.certificate.city }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Periodo</dt>
            <dd class="mt-0.5 text-sm text-slate-900">
              {{ formatDate(data.certificate.start_date) }} – {{ formatDate(data.certificate.end_date) }}
            </dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Fecha de expedición</dt>
            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ data.certificate.issue_date_label }}</dd>
          </div>
        </dl>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 text-xs text-slate-600">
          Documento emitido por <strong>{{ data.issuer.name }}</strong> (NIT {{ data.issuer.nit }}).
        </div>
      </div>

      <p class="text-center text-xs text-slate-500">
        Por protección de datos personales solo se muestran las iniciales del participante y los
        últimos cuatro dígitos del documento.
      </p>
    </div>
  </div>
</template>
