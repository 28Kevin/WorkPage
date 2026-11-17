<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import BarSeries from '@/components/BarSeries.vue'
import HeatmapGrid from '@/components/HeatmapGrid.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import StackedColumns from '@/components/StackedColumns.vue'
import AreaTrend from '@/components/AreaTrend.vue'
import DonutChart from '@/components/DonutChart.vue'
import SparkLine from '@/components/SparkLine.vue'
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

/** Métricas de actividad que se grafican como pequeños múltiplos, cada una con su propio eje. */
const ACTIVITY_CHARTS = ['login.success', 'public.search', 'public.verify', 'pdf.download']

/* ---------------------------------------------------------------- datos */
const period = ref('day')
const from = ref('')
const to = ref('')

const data = ref(null)
const loading = ref(true)
const refreshing = ref(false)
const error = ref(null)
const showTable = ref(false)

async function load(fresh = false) {
  fresh ? (refreshing.value = true) : (loading.value = true)
  error.value = null

  try {
    const { data: payload } = await api.get('/dev/usage', {
      params: {
        period: period.value,
        from: from.value || undefined,
        to: to.value || undefined,
        fresh: fresh ? 1 : undefined,
      },
    })

    data.value = payload
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

onMounted(() => load())
watch([period, from, to], () => load())

const series = computed(() =>
  (data.value?.documents ?? []).map((doc) => ({ ...doc, color: SERIES_COLORS[doc.key] })),
)

const activity = computed(() => data.value?.activity ?? null)

const activityLabel = (key) => activity.value?.metrics.find((metric) => metric.key === key)?.label ?? key

/** Serie de una métrica de actividad, en el formato de BarSeries. */
const activityRows = (key) => (activity.value?.series ?? []).map((row) => ({ label: row.label, count: row[key] }))

const busiestWeekday = computed(() => {
  const days = data.value?.by_weekday ?? []

  if (!days.some((day) => day.count > 0)) return null

  return days.reduce((best, day) => (day.count > best.count ? day : best))
})

const kpis = computed(() => {
  if (!data.value) return []

  const totals = activity.value?.totals ?? {}

  // Cada indicador lleva la forma de su serie en el rango; sin datos, no hay mini gráfica.
  const trendOf = (key) => (activity.value ? activity.value.series.map((row) => row[key]) : null)

  return [
    { key: 'docs', label: 'Documentos emitidos', value: data.value.totals.all, trend: data.value.series.map((row) => row.total) },
    {
      key: 'logins',
      label: 'Ingresos al sistema',
      value: totals['login.success'] ?? 0,
      note: `${totals['login.failed'] ?? 0} fallidos`,
      trend: trendOf('login.success'),
    },
    { key: 'search', label: 'Consultas públicas', value: totals['public.search'] ?? 0, trend: trendOf('public.search') },
    { key: 'verify', label: 'Verificaciones por QR', value: totals['public.verify'] ?? 0, trend: trendOf('public.verify') },
    { key: 'pdf', label: 'Descargas de PDF', value: totals['pdf.download'] ?? 0, trend: trendOf('pdf.download') },
    {
      key: 'peak',
      label: 'Hora pico de subida',
      value: data.value.busiest_hour?.label ?? '—',
      note: busiestWeekday.value ? `Día más activo: ${busiestWeekday.value.label}` : 'Sin registros',
    },
  ]
})

/**
 * Paneles que dependen de datos que un backend anterior no envía. Así la vista
 * no se rompe si el frontend se despliega antes que el servidor.
 */
const NEEDS = { heatmap: 'heatmap', activity: 'activity', documents: 'activity', users: 'activity', downloads: 'activity' }

function needsNewBackend(id) {
  return Boolean(NEEDS[id]) && !data.value?.[NEEDS[id]]
}

function formatRange(iso) {
  return new Date(iso).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
}

/* -------------------------------------------------- paneles ajustables */
// El orden, el tamaño y la visibilidad se recuerdan en este navegador.
const LAYOUT_KEY = 'emo.usage-layout.v2'

/** Tamaños del bento: columnas que ocupa el panel en una retícula de 4. */
const SIZES = { sm: 'lg:col-span-1', md: 'lg:col-span-2', lg: 'lg:col-span-4' }
const NEXT_SIZE = { sm: 'md', md: 'lg', lg: 'sm' }
const SIZE_LABEL = { sm: 'Pequeño', md: 'Mediano', lg: 'Ancho' }

const WIDGETS = {
  trend: 'Tendencia de documentos',
  share: 'Reparto por tipo de documento',
  series: 'Documentos emitidos en el tiempo',
  heatmap: '¿Cuándo se suben más documentos?',
  hours: 'Horas con más registros',
  weekdays: 'Días de la semana',
  activity: 'Actividad de la plataforma',
  documents: 'Documentos más consultados',
  users: 'Usuarios que más ingresan',
  downloads: 'Descargas de PDF por tipo',
}

const DEFAULT_LAYOUT = [
  { id: 'trend', size: 'md', visible: true },
  { id: 'share', size: 'md', visible: true },
  { id: 'heatmap', size: 'lg', visible: true },
  { id: 'hours', size: 'md', visible: true },
  { id: 'weekdays', size: 'md', visible: true },
  { id: 'series', size: 'lg', visible: true },
  { id: 'activity', size: 'lg', visible: true },
  { id: 'documents', size: 'md', visible: true },
  { id: 'users', size: 'md', visible: true },
  { id: 'downloads', size: 'md', visible: true },
]

/** Datos de la dona: total emitido de cada tipo, con su color fijo. */
const shareSegments = computed(() =>
  series.value.map((serie) => ({ key: serie.key, label: serie.label, color: serie.color, value: data.value?.totals[serie.key] ?? 0 })),
)

const trendRows = computed(() => (data.value?.series ?? []).map((row) => ({ label: row.label, count: row.total })))

function readLayout() {
  try {
    const saved = JSON.parse(localStorage.getItem(LAYOUT_KEY))

    if (!Array.isArray(saved)) return structuredClone(DEFAULT_LAYOUT)

    // Paneles nuevos que no estaban en lo guardado se agregan al final.
    const known = saved.filter((item) => WIDGETS[item.id])
    const missing = DEFAULT_LAYOUT.filter((item) => !known.some((saved) => saved.id === item.id))

    return [...known, ...structuredClone(missing)]
  } catch {
    return structuredClone(DEFAULT_LAYOUT)
  }
}

const layout = ref(readLayout())
const customizing = ref(false)

watch(layout, (value) => {
  try {
    localStorage.setItem(LAYOUT_KEY, JSON.stringify(value))
  } catch {
    // Sin almacenamiento el diseño dura lo que la pestaña.
  }
}, { deep: true })

const visibleWidgets = computed(() => layout.value.filter((item) => item.visible))
const hiddenWidgets = computed(() => layout.value.filter((item) => !item.visible))

function move(id, direction) {
  const index = layout.value.findIndex((item) => item.id === id)
  const target = index + direction

  if (target < 0 || target >= layout.value.length) return

  const items = [...layout.value]
  ;[items[index], items[target]] = [items[target], items[index]]
  layout.value = items
}

function widget(id) {
  return layout.value.find((item) => item.id === id)
}

function resetLayout() {
  layout.value = structuredClone(DEFAULT_LAYOUT)
}
</script>

<template>
  <div class="glass-page relative isolate -mx-4 rounded-[2rem] px-4 py-6 sm:-mx-8 sm:px-8 lg:-mx-12 lg:px-12">
    <!-- Fondo con manchas de color para que el vidrio tenga algo que difuminar. -->
    <div class="blob blob-a" aria-hidden="true"></div>
    <div class="blob blob-b" aria-hidden="true"></div>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-xs font-semibold tracking-wide text-brand-700 uppercase">Modo desarrollador</p>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Uso de la plataforma</h1>
        <p class="mt-1 text-sm text-slate-600">
          Qué se emite, cuándo se sube, quién ingresa y qué documentos se consultan.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button type="button" class="glass-btn" :aria-pressed="customizing" @click="customizing = !customizing">
          {{ customizing ? 'Listo' : 'Personalizar paneles' }}
        </button>
        <button type="button" class="glass-btn" :disabled="refreshing" @click="load(true)">
          {{ refreshing ? 'Actualizando…' : 'Actualizar ahora' }}
        </button>
      </div>
    </div>

    <!-- Filtros: en una sola fila por encima de las gráficas. -->
    <div class="glass mb-5 flex flex-wrap items-end gap-3 p-4">
      <div>
        <label for="period" class="field-label">Agrupación</label>
        <select id="period" v-model="period" class="field-input">
          <option v-for="option in PERIODS" :key="option.value" :value="option.value">{{ option.label }}</option>
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

      <button v-if="from || to" type="button" class="btn-ghost text-xs" @click="from = ''; to = ''">
        Volver al rango por defecto
      </button>

      <button type="button" class="btn-ghost ml-auto text-xs" @click="showTable = !showTable">
        {{ showTable ? 'Ocultar tabla' : 'Ver datos en tabla' }}
      </button>
    </div>

    <!-- Paneles ocultos: se recuperan desde aquí. -->
    <div v-if="customizing" class="glass mb-5 flex flex-wrap items-center gap-2 p-4 text-sm">
      <span class="font-medium text-slate-700">Paneles ocultos:</span>
      <span v-if="!hiddenWidgets.length" class="text-slate-500">ninguno</span>
      <button
        v-for="item in hiddenWidgets"
        :key="item.id"
        type="button"
        class="rounded-full border border-slate-300 bg-white/70 px-3 py-1 text-xs text-slate-700 hover:bg-white"
        @click="item.visible = true"
      >
        + {{ WIDGETS[item.id] }}
      </button>
      <button type="button" class="btn-ghost ml-auto text-xs" @click="resetLayout">Restablecer diseño</button>
    </div>

    <AlertMessage v-if="error" variant="error" class="mb-4">{{ error }}</AlertMessage>

    <LoadingSpinner v-if="loading" label="Calculando…" />

    <div v-else-if="data" class="space-y-5">
      <p class="text-xs text-slate-500">
        {{ formatRange(data.range.from) }} – {{ formatRange(data.range.to) }}
        <template v-if="data.generated_at">
          · datos calculados a las {{ formatTime(data.generated_at) }}
          (se reutilizan {{ Math.round(data.cache_seconds / 60) }} min para ahorrar consultas)
        </template>
      </p>

      <!-- Indicadores: el número es el dato; la mini gráfica solo muestra su forma. -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div v-for="kpi in kpis" :key="kpi.key" class="glass flex flex-col p-4">
          <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ kpi.label }}</p>
          <p class="mt-1 text-3xl font-bold tracking-tight text-slate-900 tabular-nums">{{ kpi.value }}</p>
          <p v-if="kpi.note" class="mt-0.5 text-xs text-slate-500">{{ kpi.note }}</p>
          <SparkLine v-if="kpi.trend && kpi.trend.length" :values="kpi.trend" class="mt-auto pt-3" />
        </div>
      </div>

      <!-- Bento: paneles de 1, 2 o 4 columnas en el orden elegido. -->
      <div class="grid grid-flow-row-dense gap-5 lg:grid-cols-4">
        <section
          v-for="(item, position) in visibleWidgets"
          :key="item.id"
          class="glass p-5"
          :class="SIZES[item.size] || SIZES.md"
        >
          <header class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="text-sm font-bold text-slate-900">{{ WIDGETS[item.id] }}</h2>

              <p v-if="item.id === 'series' || item.id === 'trend'" class="text-xs text-slate-500">
                {{ PERIODS.find((p) => p.value === period).label }}
              </p>
              <p v-else-if="item.id === 'hours' || item.id === 'heatmap'" class="text-xs text-slate-500">
                <template v-if="data.busiest_hour">
                  La hora más activa es las {{ data.busiest_hour.label }}, con {{ data.busiest_hour.count }} documento(s).
                </template>
                <template v-else>Sin registros en el rango.</template>
              </p>
              <p v-else-if="item.id === 'activity'" class="text-xs text-slate-500">
                Cada gráfica tiene su propia escala: compare formas, no alturas entre ellas.
              </p>
              <p v-else-if="item.id === 'documents'" class="text-xs text-slate-500">
                Veces que se abrió su verificación por código QR.
              </p>
            </div>

            <!-- Controles del panel: solo al personalizar. -->
            <div v-if="customizing" class="flex gap-1">
              <button type="button" class="panel-btn" :disabled="position === 0" aria-label="Subir panel" @click="move(item.id, -1)">↑</button>
              <button type="button" class="panel-btn" aria-label="Bajar panel" @click="move(item.id, 1)">↓</button>
              <button
                type="button"
                class="panel-btn"
                style="width: auto; padding-inline: 0.5rem"
                :aria-label="`Tamaño: ${SIZE_LABEL[item.size]}. Cambiar`"
                @click="widget(item.id).size = NEXT_SIZE[item.size] || 'md'"
              >
                {{ SIZE_LABEL[item.size] || 'Mediano' }}
              </button>
              <button type="button" class="panel-btn" aria-label="Ocultar panel" @click="widget(item.id).visible = false">✕</button>
            </div>

            <!-- Leyenda: la identidad nunca depende solo del color. -->
            <ul v-else-if="item.id === 'series'" class="flex flex-wrap gap-x-4 gap-y-1">
              <li v-for="serie in series" :key="serie.key" class="flex items-center gap-1.5 text-xs text-slate-600">
                <span class="inline-block h-2.5 w-2.5 rounded-sm" :style="{ background: serie.color }"></span>
                {{ serie.label }}
              </li>
            </ul>
          </header>

          <!-- ------------------------------------------------- contenido -->
          <p v-if="needsNewBackend(item.id)" class="rounded-xl bg-amber-50 px-4 py-6 text-center text-sm text-amber-800">
            El servidor todavía no envía estos datos. Aparecerán al desplegar la nueva versión del backend.
          </p>

          <template v-else-if="item.id === 'trend'">
            <AreaTrend v-if="data.totals.all" :rows="trendRows" />
            <p v-else class="py-10 text-center text-sm text-slate-500">No se emitió ningún documento en este rango.</p>
          </template>

          <DonutChart v-else-if="item.id === 'share'" :segments="shareSegments" center-label="documentos" />

          <template v-else-if="item.id === 'series'">
            <StackedColumns v-if="data.totals.all" :rows="data.series" :series="series" />
            <p v-else class="py-10 text-center text-sm text-slate-500">No se emitió ningún documento en este rango.</p>
          </template>

          <HeatmapGrid
            v-else-if="item.id === 'heatmap'"
            :cells="data.heatmap.cells"
            :weekdays="data.heatmap.weekdays"
            :max="data.heatmap.max"
          />

          <BarSeries v-else-if="item.id === 'hours'" :rows="data.by_hour" :label-every="3" unit="documento(s)" />

          <BarSeries v-else-if="item.id === 'weekdays'" :rows="data.by_weekday" unit="documento(s)" />

          <div v-else-if="item.id === 'activity'" class="grid gap-6 md:grid-cols-2">
            <div v-for="key in ACTIVITY_CHARTS" :key="key">
              <p class="mb-2 flex items-baseline justify-between text-xs font-semibold text-slate-700">
                {{ activityLabel(key) }}
                <span class="font-mono text-slate-500 tabular-nums">{{ activity.totals[key] }}</span>
              </p>
              <BarSeries :rows="activityRows(key)" :label-every="Math.ceil(activity.series.length / 8)" unit="vez/veces" />
            </div>
          </div>

          <template v-else-if="item.id === 'documents'">
            <ol v-if="activity.top_documents.length" class="space-y-2">
              <li v-for="(doc, rank) in activity.top_documents" :key="doc.order_code + rank" class="rank-row">
                <span class="w-5 text-xs font-bold text-slate-400">{{ rank + 1 }}</span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-mono text-sm font-semibold text-slate-900">{{ doc.order_code }}</span>
                  <span class="block text-xs text-slate-500">{{ doc.type }}</span>
                </span>
                <span class="rank-bar" :style="{ '--share': doc.count / activity.top_documents[0].count }"></span>
                <span class="w-10 text-right font-mono text-sm tabular-nums text-slate-900">{{ doc.count }}</span>
              </li>
            </ol>
            <p v-else class="py-8 text-center text-sm text-slate-500">Ningún documento se consultó por QR en este rango.</p>
          </template>

          <template v-else-if="item.id === 'users'">
            <ol v-if="activity.top_users.length" class="space-y-2">
              <li v-for="(user, rank) in activity.top_users" :key="user.name + rank" class="rank-row">
                <span class="w-5 text-xs font-bold text-slate-400">{{ rank + 1 }}</span>
                <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-900">{{ user.name }}</span>
                <span class="rank-bar" :style="{ '--share': user.count / activity.top_users[0].count }"></span>
                <span class="w-10 text-right font-mono text-sm tabular-nums text-slate-900">{{ user.count }}</span>
              </li>
            </ol>
            <p v-else class="py-8 text-center text-sm text-slate-500">Nadie ingresó en este rango.</p>
          </template>

          <BarSeries v-else-if="item.id === 'downloads'" :rows="activity.downloads_by_type" unit="descarga(s)" />
        </section>
      </div>

      <!-- Vista de tabla: deja los valores disponibles sin depender del color. -->
      <section v-if="showTable" class="glass overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-slate-200 text-left">
            <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <th class="px-4 py-3">Intervalo</th>
              <th v-for="serie in series" :key="serie.key" class="px-4 py-3 text-right">{{ serie.label }}</th>
              <th class="px-4 py-3 text-right">Total</th>
              <th v-for="key in ACTIVITY_CHARTS" :key="key" class="px-4 py-3 text-right">{{ activityLabel(key) }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="(row, index) in data.series" :key="row.bucket" class="hover:bg-white/60">
              <td class="px-4 py-2 text-slate-700">{{ row.label }}</td>
              <td v-for="serie in series" :key="serie.key" class="px-4 py-2 text-right font-mono tabular-nums text-slate-700">
                {{ row[serie.key] }}
              </td>
              <td class="px-4 py-2 text-right font-mono font-semibold tabular-nums text-slate-900">{{ row.total }}</td>
              <td v-for="key in ACTIVITY_CHARTS" :key="key" class="px-4 py-2 text-right font-mono tabular-nums text-slate-700">
                {{ activity?.series[index]?.[key] ?? '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>
</template>

<style scoped>
.glass-page {
  background: linear-gradient(135deg, var(--color-brand-50), #ffffff 45%, var(--color-accent-50));
}

.blob {
  position: absolute;
  z-index: -1;
  width: 28rem;
  height: 28rem;
  border-radius: 999px;
  filter: blur(80px);
  opacity: 0.35;
  pointer-events: none;
}

.blob-a { top: 2rem; left: -6rem; background: var(--color-brand-300); }
.blob-b { right: -6rem; bottom: 4rem; background: var(--color-accent-300); }

/* Panel de vidrio esmerilado. */
.glass {
  border-radius: 1.25rem;
  border: 1px solid rgb(255 255 255 / 0.7);
  background: rgb(255 255 255 / 0.6);
  box-shadow: 0 8px 32px -12px rgb(15 23 42 / 0.15);
  backdrop-filter: blur(16px) saturate(1.4);
}

.glass-btn {
  border-radius: 999px;
  border: 1px solid rgb(255 255 255 / 0.8);
  background: rgb(255 255 255 / 0.7);
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-slate-700);
  backdrop-filter: blur(12px);
  transition: background-color 0.2s;
}

.glass-btn:hover { background: white; }
.glass-btn:disabled { opacity: 0.6; }

.panel-btn {
  display: inline-flex;
  width: 2rem;
  height: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.6rem;
  border: 1px solid var(--color-slate-200);
  background: white;
  font-size: 0.75rem;
  color: var(--color-slate-600);
}

.panel-btn:hover { background: var(--color-slate-50); }
.panel-btn:disabled { opacity: 0.35; }

.rank-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

/* Barra proporcional al primero de la lista: magnitud en un solo tono. */
.rank-bar {
  height: 0.5rem;
  width: calc(var(--share) * 6rem);
  min-width: 0.25rem;
  border-radius: 999px;
  background: #2a78d6;
}
</style>
