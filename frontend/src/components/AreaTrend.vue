<script setup>
import { computed, ref } from 'vue'

/*
 * Tendencia de una sola serie en el tiempo: línea de 2px con relleno suave,
 * rejilla discreta y cursor vertical con tooltip al pasar el mouse.
 */
const props = defineProps({
  /** [{ label, count }] */
  rows: { type: Array, required: true },
  color: { type: String, default: '#2a78d6' },
  unit: { type: String, default: 'documento(s)' },
})

const W = 640
const H = 220
const PAD = { top: 12, right: 12, bottom: 26, left: 32 }
const plotW = W - PAD.left - PAD.right
const plotH = H - PAD.top - PAD.bottom

/** Máximo redondeado hacia arriba a un número "amable" para la rejilla. */
const max = computed(() => {
  const raw = Math.max(1, ...props.rows.map((row) => row.count))
  const magnitude = 10 ** Math.floor(Math.log10(raw))

  return Math.ceil(raw / magnitude) * magnitude
})

const ticks = computed(() => [0, 0.5, 1].map((share) => Math.round(max.value * share)))

function x(index) {
  return PAD.left + (props.rows.length > 1 ? (index / (props.rows.length - 1)) * plotW : plotW / 2)
}

function y(value) {
  return PAD.top + plotH - (value / max.value) * plotH
}

const line = computed(() => props.rows.map((row, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(row.count).toFixed(1)}`).join(' '))
const area = computed(() => `${line.value} L${x(props.rows.length - 1)},${PAD.top + plotH} L${x(0)},${PAD.top + plotH} Z`)

/** Unas seis etiquetas en el eje x, sin amontonarse. */
const labelEvery = computed(() => Math.max(1, Math.ceil(props.rows.length / 6)))

const hovered = ref(null)
const svg = ref(null)

function onMove(event) {
  const box = svg.value.getBoundingClientRect()
  const relative = ((event.clientX - box.left) / box.width) * W - PAD.left
  const index = Math.round((relative / plotW) * (props.rows.length - 1))

  hovered.value = Math.min(Math.max(index, 0), props.rows.length - 1)
}

const tooltip = computed(() => (hovered.value === null ? null : props.rows[hovered.value]))
const gradientId = `area-${Math.random().toString(36).slice(2, 9)}`
</script>

<template>
  <div class="relative">
    <svg
      ref="svg"
      :viewBox="`0 0 ${W} ${H}`"
      class="h-auto w-full touch-none"
      role="img"
      :aria-label="`Tendencia de ${unit} en el tiempo`"
      @pointermove="onMove"
      @pointerleave="hovered = null"
    >
      <defs>
        <linearGradient :id="gradientId" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0" :stop-color="color" stop-opacity="0.22" />
          <stop offset="1" :stop-color="color" stop-opacity="0" />
        </linearGradient>
      </defs>

      <!-- Rejilla recesiva y eje y con tres marcas. -->
      <g v-for="tick in ticks" :key="tick">
        <line :x1="PAD.left" :x2="W - PAD.right" :y1="y(tick)" :y2="y(tick)" stroke="#e2e8f0" stroke-width="1" />
        <text :x="PAD.left - 6" :y="y(tick) + 3" text-anchor="end" class="fill-slate-400 text-[10px]">{{ tick }}</text>
      </g>

      <template v-for="(row, index) in rows" :key="row.label">
        <text
          v-if="index % labelEvery === 0"
          :x="x(index)"
          :y="H - 8"
          text-anchor="middle"
          class="fill-slate-400 text-[10px]"
        >{{ row.label }}</text>
      </template>

      <path :d="area" :fill="`url(#${gradientId})`" />
      <path :d="line" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

      <!-- Cursor: línea vertical y punto con anillo del color de superficie. -->
      <g v-if="tooltip">
        <line :x1="x(hovered)" :x2="x(hovered)" :y1="PAD.top" :y2="PAD.top + plotH" stroke="#94a3b8" stroke-dasharray="3 3" />
        <circle :cx="x(hovered)" :cy="y(tooltip.count)" r="5" :fill="color" stroke="white" stroke-width="2" />
      </g>
    </svg>

    <div
      v-if="tooltip"
      class="pointer-events-none absolute top-0 rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs text-white shadow-lg"
      :style="{ left: `${(x(hovered) / W) * 100}%`, transform: `translateX(${hovered > rows.length / 2 ? '-105%' : '5%'})` }"
    >
      <p class="text-white/70">{{ tooltip.label }}</p>
      <p class="font-semibold tabular-nums">{{ tooltip.count }} {{ unit }}</p>
    </div>
  </div>
</template>
