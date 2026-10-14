<script setup>
import { computed, ref } from 'vue'

/**
 * Columnas apiladas: cuántos documentos se emitieron en cada intervalo,
 * repartidos por tipo.
 *
 * Se dibuja en SVG a mano en vez de traer una librería de gráficas: el panel
 * ya cuidaba el peso de la descarga y esto son unas decenas de líneas.
 */
const props = defineProps({
  // [{ label, total, <clave de serie>: n }]
  rows: { type: Array, required: true },
  // [{ key, label, color }]
  series: { type: Array, required: true },
})

const W = 840
const H = 280
const PAD = { top: 14, right: 10, bottom: 30, left: 46 }

/** Separación en color de fondo entre segmentos que se tocan. */
const GAP = 2
const RADIUS = 4
const MAX_BAR = 24

const plotW = W - PAD.left - PAD.right
const plotH = H - PAD.top - PAD.bottom

const hovered = ref(null)

const max = computed(() => Math.max(1, ...props.rows.map((r) => r.total)))

/** Marcas del eje en números redondos. */
const ticks = computed(() => {
  const step = niceStep(max.value / 4)
  const top = Math.ceil(max.value / step) * step
  const out = []

  for (let v = 0; v <= top; v += step) out.push(v)

  return { values: out, top }
})

function niceStep(raw) {
  const pow = 10 ** Math.floor(Math.log10(Math.max(raw, 1)))
  const n = raw / pow

  return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * pow
}

const band = computed(() => plotW / Math.max(props.rows.length, 1))
const barWidth = computed(() => Math.min(MAX_BAR, band.value * 0.7))

function y(value) {
  return PAD.top + plotH - (value / ticks.value.top) * plotH
}

function bandX(index) {
  return PAD.left + band.value * index
}

function barX(index) {
  return bandX(index) + (band.value - barWidth.value) / 2
}

/**
 * Segmentos de una columna, de abajo arriba. El de más arriba lleva el
 * extremo redondeado; el resto quedan rectos sobre la línea base.
 */
function segments(row) {
  const out = []
  let bottom = PAD.top + plotH
  const last = props.series.filter((s) => row[s.key] > 0).at(-1)

  props.series.forEach((serie) => {
    const value = row[serie.key] || 0

    if (!value) return

    const height = (value / ticks.value.top) * plotH
    const top = bottom - height
    // El hueco separa este segmento del de abajo sin dibujar un borde.
    const visible = Math.max(height - (out.length ? GAP : 0), 1)

    out.push({
      key: serie.key,
      color: serie.color,
      path: barPath(top, visible, serie.key === last?.key),
    })

    bottom = top
  })

  return out
}

/** Rectángulo con el extremo superior redondeado si es el final del dato. */
function barPath(top, height, rounded) {
  const w = barWidth.value
  const r = rounded ? Math.min(RADIUS, height / 2, w / 2) : 0
  const bottom = top + height

  return `M0,${bottom} L0,${top + r} Q0,${top} ${r},${top} L${w - r},${top} Q${w},${top} ${w},${top + r} L${w},${bottom} Z`
}

/** Una de cada n etiquetas para que no se amontonen. */
const labelEvery = computed(() => Math.ceil(props.rows.length / 14))

const tooltip = computed(() => {
  if (hovered.value === null) return null

  const row = props.rows[hovered.value]

  return {
    label: row.label,
    total: row.total,
    x: bandX(hovered.value) + band.value / 2,
    items: props.series.filter((s) => row[s.key] > 0).map((s) => ({ ...s, value: row[s.key] })),
  }
})
</script>

<template>
  <div class="relative">
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" aria-label="Documentos emitidos por intervalo">
      <!-- Rejilla: línea fina continua, un paso por encima del fondo. -->
      <g>
        <line
          v-for="value in ticks.values"
          :key="`g${value}`"
          :x1="PAD.left"
          :x2="W - PAD.right"
          :y1="y(value)"
          :y2="y(value)"
          stroke="#e1e0d9"
          stroke-width="1"
        />
        <text
          v-for="value in ticks.values"
          :key="`t${value}`"
          :x="PAD.left - 8"
          :y="y(value) + 4"
          text-anchor="end"
          font-size="11"
          fill="#898781"
        >{{ value }}</text>
      </g>

      <!-- Columnas -->
      <g v-for="(row, index) in rows" :key="row.bucket">
        <g :transform="`translate(${barX(index)}, 0)`">
          <path
            v-for="segment in segments(row)"
            :key="segment.key"
            :d="segment.path"
            :fill="segment.color"
            :opacity="hovered === null || hovered === index ? 1 : 0.35"
          />
        </g>

        <!-- Zona de contacto más ancha que la columna, para el puntero. -->
        <rect
          :x="bandX(index)"
          :y="PAD.top"
          :width="band"
          :height="plotH"
          fill="transparent"
          @mouseenter="hovered = index"
          @mouseleave="hovered = null"
        />

        <text
          v-if="index % labelEvery === 0"
          :x="bandX(index) + band / 2"
          :y="H - 10"
          text-anchor="middle"
          font-size="11"
          fill="#898781"
        >{{ row.label }}</text>
      </g>

      <!-- Línea base -->
      <line
        :x1="PAD.left"
        :x2="W - PAD.right"
        :y1="PAD.top + plotH"
        :y2="PAD.top + plotH"
        stroke="#c3c2b7"
        stroke-width="1"
      />
    </svg>

    <div
      v-if="tooltip"
      class="pointer-events-none absolute top-2 z-10 min-w-40 -translate-x-1/2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg"
      :style="{ left: `${(tooltip.x / W) * 100}%` }"
    >
      <p class="font-semibold text-slate-900">{{ tooltip.label }}</p>
      <p class="mb-1 text-slate-500">{{ tooltip.total }} documento(s)</p>
      <p v-for="item in tooltip.items" :key="item.key" class="flex items-center gap-1.5 text-slate-700">
        <span class="inline-block h-2 w-2 rounded-full" :style="{ background: item.color }"></span>
        {{ item.label }}: <strong>{{ item.value }}</strong>
      </p>
    </div>
  </div>
</template>
