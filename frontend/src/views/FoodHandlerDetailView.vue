<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { downloadFoodHandlerPdf, openFoodHandlerPdf } from '@/services/pdf'

const route = useRoute()

const record = ref(null)
const loading = ref(true)
const error = ref(null)
const notice = ref(null)
const busy = ref(null)

onMounted(async () => {
  if (route.query.created) notice.value = 'El paquete fue generado correctamente.'
  if (route.query.updated) notice.value = 'Las correcciones fueron guardadas.'

  try {
    const { data } = await api.get(`/food-handler/${route.params.id}`)
    record.value = data.data
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
})

async function download() {
  busy.value = 'download'

  try {
    await downloadFoodHandlerPdf(record.value.id)
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    busy.value = null
  }
}

async function preview() {
  busy.value = 'preview'

  try {
    await openFoodHandlerPdf(record.value.id)
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    busy.value = null
  }
}

function formatDate(value) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('es-CO')
}
</script>

<template>
  <div>
    <LoadingSpinner v-if="loading" label="Cargando documento…" />

    <AlertMessage v-else-if="error" variant="error">{{ error }}</AlertMessage>

    <div v-else-if="record">
      <AlertMessage v-if="notice" variant="success" class="mb-4">{{ notice }}</AlertMessage>

      <AlertMessage v-if="record.annulment.annulled" variant="error" class="mb-4">
        Documento anulado el {{ new Date(record.annulment.annulled_at).toLocaleDateString('es-CO') }}.
        Motivo: {{ record.annulment.reason }}
      </AlertMessage>

      <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="text-xs font-semibold tracking-wide text-brand-700 uppercase">
            {{ record.document_type_label }}
          </p>
          <h1 class="font-mono text-2xl font-bold text-slate-900">{{ record.order_code }}</h1>
          <p class="mt-1 text-sm text-slate-600">
            Expedido el {{ formatDate(record.issue_date) }}
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <RouterLink :to="{ name: 'admin.food' }" class="btn-secondary">Volver</RouterLink>

          <RouterLink
            v-if="!record.annulment.annulled"
            :to="{ name: 'admin.food.edit', params: { id: record.id } }"
            class="btn-secondary"
          >
            Editar
          </RouterLink>

          <button type="button" class="btn-secondary" :disabled="busy" @click="preview">
            {{ busy === 'preview' ? 'Abriendo…' : 'Previsualizar' }}
          </button>

          <button type="button" class="btn-primary" :disabled="busy" @click="download">
            {{ busy === 'download' ? 'Generando…' : 'Descargar PDF' }}
          </button>
        </div>
      </div>

      <div class="space-y-5">
        <section class="card">
          <header class="section-heading">
            <span class="section-badge">A</span>
            <h2 class="text-sm font-bold text-slate-900">Paciente</h2>
          </header>

          <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="row-span-2 flex h-28 w-24 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
              <img v-if="record.patient.photo" :src="record.patient.photo" alt="Fotografía" class="h-full w-full object-cover">
              <span v-else class="px-1 text-center text-xs text-slate-400">Sin foto</span>
            </div>

            <div>
              <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Nombre</p>
              <p class="mt-0.5 text-sm text-slate-900">{{ record.patient.full_name }}</p>
            </div>

            <div>
              <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Documento</p>
              <p class="mt-0.5 text-sm text-slate-900">{{ record.patient.document_number }}</p>
            </div>

            <div>
              <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Edad</p>
              <p class="mt-0.5 text-sm text-slate-900">{{ record.patient.age }} años</p>
            </div>
          </div>
        </section>

        <section v-for="panel in record.lab_results" :key="panel.key" class="card">
          <header class="section-heading">
            <h2 class="text-sm font-bold text-slate-900">{{ panel.label }}</h2>
            <span class="ml-auto text-xs text-slate-500">Página {{ panel.page }}</span>
          </header>

          <div class="space-y-4 p-5">
            <div v-for="group in panel.groups" :key="group.key">
              <p v-if="panel.groups.length > 1" class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                {{ group.label }}
              </p>

              <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="field in group.fields" :key="field.key">
                  <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ field.label }}</dt>
                  <dd class="mt-0.5 text-sm text-slate-900">{{ field.value || '—' }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </section>

        <section class="card">
          <header class="section-heading">
            <h2 class="text-sm font-bold text-slate-900">Verificación pública</h2>
          </header>

          <div class="p-5">
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Código</p>
            <p class="mt-0.5 font-mono text-sm text-slate-900">{{ record.verification.code }}</p>

            <a
              :href="record.verification.url"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-2 inline-block text-xs font-medium text-sky-700 hover:underline"
            >
              Abrir página de verificación ↗
            </a>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
