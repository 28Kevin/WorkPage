<script setup>
import { computed, ref } from 'vue'

/**
 * Barras de una sola serie (reparto por hora del día o por día de la semana).
 * Al ser una sola serie no lleva leyenda: el título ya dice qué se grafica.
 */
const props = defineProps({
  // [{ label, count }]
  rows: { type: Array, required: true },
  color: { type: String, default: '#2a78d6' },
  /** Una de cada n etiquetas, para que no se amontonen con 24 barras. */
  labelEvery: { type: Number, default: 1 },
  unit: { type: String, default: 'registro(s)' },
})

const W = 840
const H = 200
const PAD = { top: 18, right: 10, bottom: 28, left: 40 }

const GAP = 2
const RADIUS = 4
const MAX_BAR = 24

const plotW = W - PAD.left - PAD.right
const plotH = H - PAD.top - PAD.bottom

const hovered = ref(null)

const max = computed(() => Math.max(1, ...props.rows.map((r) => r.count)))

/** Índice del valor más alto: es el único que se etiqueta directamente. */
const peak = computed(() => {
  if (!props.rows.some((r) => r.count > 0)) return null

  return props.rows.reduce((best, row, i) => (row.count > props.rows[best].count ? i : best), 0)
})

const band = computed(() => plotW / Math.max(props.rows.length, 1))
// El hueco entre barras contiguas lo hace el fondo, no un borde.
const barWidth = computed(() => Math.min(MAX_BAR, Math.max(band.value - GAP, 2)))

function bandX(index) {
  return PAD.left + band.value * index
}

function barX(index) {
  return bandX(index) + (band.value - barWidth.value) / 2
}

function barHeight(count) {
  return count === 0 ? 0 : Math.max((count / max.value) * plotH, 2)
}

function barY(count) {
  return PAD.top + plotH - barHeight(count)
}

function path(count) {
  const h = barHeight(count)

  if (h === 0) return ''

  const w = barWidth.value
  const r = Math.min(RADIUS, h / 2, w / 2)
  const top = barY(count)
  const bottom = PAD.top + plotH

  return `M0,${bottom} L0,${top + r} Q0,${top} ${r},${top} L${w - r},${top} Q${w},${top} ${w},${top + r} L${w},${bottom} Z`
}

const tooltip = computed(() => {
  if (hovered.value === null) return null

  const row = props.rows[hovered.value]

  return { label: row.label, count: row.count, x: bandX(hovered.value) + band.value / 2 }
})
</script>

<template>
  <div class="relative">
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" aria-label="Distribución de registros">
      <line
        :x1="PAD.left"
        :x2="W - PAD.right"
        :y1="PAD.top"
        :y2="PAD.top"
        stroke="#e1e0d9"
        stroke-width="1"
      />

      <g v-for="(row, index) in rows" :key="row.label">
        <path
          :transform="`translate(${barX(index)}, 0)`"
          :d="path(row.count)"
          :fill="color"
          :opacity="hovered === null || hovered === index ? 1 : 0.35"
        />

        <!-- Solo se etiqueta el máximo: un número sobre cada barra no se lee. -->
        <text
          v-if="peak === index && row.count > 0"
          :x="bandX(index) + band / 2"
          :y="barY(row.count) - 6"
          text-anchor="middle"
          font-size="11"
          font-weight="700"
          fill="#0b0b0b"
        >{{ row.count }}</text>

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
          :y="H - 9"
          text-anchor="middle"
          font-size="11"
          fill="#898781"
        >{{ row.label }}</text>
      </g>

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
      class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs shadow-lg"
      :style="{ left: `${(tooltip.x / W) * 100}%` }"
    >
      <span class="font-semibold text-slate-900">{{ tooltip.label }}</span>
      <span class="text-slate-600"> · {{ tooltip.count }} {{ unit }}</span>
    </div>
  </div>
</template>
