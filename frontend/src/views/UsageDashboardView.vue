<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import BarSeries from '@/components/BarSeries.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import StackedColumns from '@/components/StackedColumns.vue'
import api, { parseApiError } from '@/services/api'

/**
 * Paleta categórica validada para daltonismo (ΔE adyacente ≥ 8). El orden es
 * fijo: cada tipo de documento conserva su color aunque cambien los filtros.
 * No usa el color de marca porque ese es configurable y podría colisionar.
 */
const SERIES_COLORS = {
  medical_exam: '#2a78d6',
  food_handler: '#eb6834',
  training_certificate: '#1baf7a',
  billing_account: '#eda100',
}

const PERIODS = [
  { value: 'hour', label: 'Por hora' },
  { value: 'day', label: 'Por día' },
  { value: 'week', label: 'Por semana' },
  { value: 'month', label: 'Por mes' },
]

const period = ref('day')
const from = ref('')
const to = ref('')

const data = ref(null)
const loading = ref(true)
const error = ref(null)
const showTable = ref(false)

async function load() {
  loading.value = true
  error.value = null

  try {
    const { data: payload } = await api.get('/dev/usage', {
      params: {
        period: period.value,
        from: from.value || undefined,
        to: to.value || undefined,
      },
    })

    data.value = payload
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch([period, from, to], load)

/** Series con su color fijo, en el orden que define el servidor. */
const series = computed(() =>
  (data.value?.documents ?? []).map((doc) => ({ ...doc, color: SERIES_COLORS[doc.key] })),
)

const tiles = computed(() => {
  if (!data.value) return []

  return series.value.map((serie) => ({
    ...serie,
    value: data.value.totals[serie.key] ?? 0,
  }))
})

const hasActivity = computed(() => (data.value?.totals.all ?? 0) > 0)

function formatRange(iso) {
  return new Date(iso).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div>
    <div class="mb-6">
      <p class="text-xs font-semibold tracking-wide text-brand-700 uppercase">Modo desarrollador</p>
      <h1 class="text-2xl font-bold text-slate-900">Uso de la plataforma</h1>
      <p class="mt-1 text-sm text-slate-600">
        Documentos emitidos y a qué horas se registran. Solo visible para cuentas de desarrollo.
      </p>
    </div>

    <!-- Filtros: en una sola fila por encima de las gráficas. -->
    <div class="card mb-5 flex flex-wrap items-end gap-3 p-4">
      <div>
        <label for="period" class="field-label">Agrupación</label>
        <select id="period" v-model="period" class="field-input">
          <option v-for="option in PERIODS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>

      <div>
        <label for="from" class="field-label">Desde</label>
        <input id="from" v-model="from" type="date" class="field-input">
      </div>

      <div>
        <label for="to" class="field-label">Hasta</label>
        <input id="to" v-model="to" type="date" class="field-input">
      </div>

      <button
        v-if="from || to"
        type="button"
        class="btn-ghost text-xs"
        @click="from = ''; to = ''"
      >
        Volver al rango por defecto
      </button>

      <button type="button" class="btn-ghost ml-auto text-xs" @click="showTable = !showTable">
        {{ showTable ? 'Ocultar tabla' : 'Ver datos en tabla' }}
      </button>
    </div>

    <AlertMessage v-if="error" variant="error" class="mb-4">{{ error }}</AlertMessage>

    <LoadingSpinner v-if="loading" label="Calculando…" />

    <div v-else-if="data" class="space-y-5">
      <p class="text-xs text-slate-500">
        {{ formatRange(data.range.from) }} – {{ formatRange(data.range.to) }}
      </p>

      <!-- Totales: el número es el dato, no hace falta gráfica. -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="card p-4">
          <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Total</p>
          <p class="mt-1 text-3xl font-bold text-slate-900">{{ data.totals.all }}</p>
          <p class="text-xs text-slate-500">documentos emitidos</p>
        </div>

        <div v-for="tile in tiles" :key="tile.key" class="card p-4">
          <p class="flex items-center gap-1.5 text-xs font-medium tracking-wide text-slate-500 uppercase">
            <span class="inline-block h-2.5 w-2.5 rounded-sm" :style="{ background: tile.color }"></span>
            {{ tile.label }}
          </p>
          <p class="mt-1 text-3xl font-bold text-slate-900">{{ tile.value }}</p>
        </div>
      </div>

      <div v-if="!hasActivity" class="card px-6 py-12 text-center">
        <p class="text-sm font-medium text-slate-900">No se emitió ningún documento en este rango</p>
        <p class="mt-1 text-sm text-slate-500">Amplíe las fechas o cambie la agrupación.</p>
      </div>

      <template v-else>
        <!-- Serie temporal -->
        <section class="card p-5">
          <header class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-sm font-bold text-slate-900">Documentos emitidos</h2>
              <p class="text-xs text-slate-500">{{ PERIODS.find((p) => p.value === period).label }}</p>
            </div>

            <!-- Leyenda: la identidad nunca depende solo del color. -->
            <ul class="flex flex-wrap gap-x-4 gap-y-1">
              <li v-for="serie in series" :key="serie.key" class="flex items-center gap-1.5 text-xs text-slate-600">
                <span class="inline-block h-2.5 w-2.5 rounded-sm" :style="{ background: serie.color }"></span>
                {{ serie.label }}
              </li>
            </ul>
          </header>

          <StackedColumns :rows="data.series" :series="series" />
        </section>

        <!-- Hora del día -->
        <section class="card p-5">
          <header class="mb-3">
            <h2 class="text-sm font-bold text-slate-900">Horas con más registros</h2>
            <p class="text-xs text-slate-500">
              <template v-if="data.busiest_hour">
                La hora más activa es las {{ data.busiest_hour.label }}, con
                {{ data.busiest_hour.count }} registro(s).
              </template>
              <template v-else>Sin registros en el rango.</template>
            </p>
          </header>

          <BarSeries :rows="data.by_hour" :label-every="2" />
        </section>

        <!-- Día de la semana -->
        <section class="card p-5">
          <header class="mb-3">
            <h2 class="text-sm font-bold text-slate-900">Días de la semana</h2>
          </header>

          <BarSeries :rows="data.by_weekday" />
        </section>
      </template>

      <!-- Vista de tabla: deja los valores disponibles sin depender del color. -->
      <section v-if="showTable" class="card overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-left">
            <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <th class="px-4 py-3">Intervalo</th>
              <th v-for="serie in series" :key="serie.key" class="px-4 py-3 text-right">{{ serie.label }}</th>
              <th class="px-4 py-3 text-right">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in data.series" :key="row.bucket" class="hover:bg-slate-50">
              <td class="px-4 py-2 text-slate-700">{{ row.label }}</td>
              <td
                v-for="serie in series"
                :key="serie.key"
                class="px-4 py-2 text-right font-mono tabular-nums text-slate-700"
              >{{ row[serie.key] }}</td>
              <td class="px-4 py-2 text-right font-mono font-semibold tabular-nums text-slate-900">
                {{ row.total }}
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>
</template>
