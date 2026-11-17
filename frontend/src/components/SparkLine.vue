<script setup>
import { computed } from 'vue'

/*
 * Mini gráfica de tendencia para un indicador. Sin ejes ni etiquetas: el
 * número del tile es el dato; esto solo muestra la forma.
 */
const props = defineProps({
  values: { type: Array, required: true },
  color: { type: String, default: '#2a78d6' },
})

const W = 120
const H = 36

const points = computed(() => {
  const values = props.values.length > 1 ? props.values : [0, ...props.values, 0]
  const max = Math.max(1, ...values)
  const step = W / (values.length - 1)

  return values.map((value, index) => [index * step, H - 3 - (value / max) * (H - 6)])
})

const line = computed(() => points.value.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)},${y.toFixed(1)}`).join(' '))
const area = computed(() => `${line.value} L${W},${H} L0,${H} Z`)

const gradientId = `spark-${Math.random().toString(36).slice(2, 9)}`
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" class="h-9 w-full" aria-hidden="true">
    <defs>
      <linearGradient :id="gradientId" x1="0" x2="0" y1="0" y2="1">
        <stop offset="0" :stop-color="color" stop-opacity="0.25" />
        <stop offset="1" :stop-color="color" stop-opacity="0" />
      </linearGradient>
    </defs>
    <path :d="area" :fill="`url(#${gradientId})`" />
    <path :d="line" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
  </svg>
</template>
