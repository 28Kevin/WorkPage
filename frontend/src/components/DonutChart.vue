<script setup>
import { computed, ref } from 'vue'

/*
 * Reparto de un total entre pocas categorías. Cada segmento lleva 2px de
 * separación con el color de superficie, y la leyenda repite nombre, valor y
 * porcentaje para que la identidad no dependa solo del color.
 */
const props = defineProps({
  /** [{ key, label, value, color }] */
  segments: { type: Array, required: true },
  centerLabel: { type: String, default: 'total' },
})

const R = 42
const STROKE = 14
const C = 2 * Math.PI * R
const GAP = 2

const total = computed(() => props.segments.reduce((sum, segment) => sum + segment.value, 0))

const arcs = computed(() => {
  let offset = 0
  const visible = props.segments.filter((segment) => segment.value > 0)

  return visible.map((segment) => {
    const length = (segment.value / total.value) * C
    const arc = {
      ...segment,
      dash: `${Math.max(length - (visible.length > 1 ? GAP : 0), 0.5)} ${C}`,
      offset: -offset,
    }

    offset += length

    return arc
  })
})

const hovered = ref(null)

function share(value) {
  return total.value ? Math.round((value / total.value) * 100) : 0
}
</script>

<template>
  <div class="flex flex-col items-center gap-5 sm:flex-row">
    <svg viewBox="0 0 100 100" class="h-40 w-40 shrink-0 -rotate-90" role="img" :aria-label="`Reparto de ${total} ${centerLabel}`">
      <circle cx="50" cy="50" :r="R" fill="none" stroke="#f1f5f9" :stroke-width="STROKE" />
      <circle
        v-for="arc in arcs"
        :key="arc.key"
        cx="50"
        cy="50"
        :r="R"
        fill="none"
        :stroke="arc.color"
        :stroke-width="hovered === arc.key ? STROKE + 3 : STROKE"
        :stroke-dasharray="arc.dash"
        :stroke-dashoffset="arc.offset"
        class="transition-[stroke-width] duration-200"
        @pointerenter="hovered = arc.key"
        @pointerleave="hovered = null"
      />
      <text x="50" y="50" text-anchor="middle" dominant-baseline="central" transform="rotate(90 50 50)"
            class="fill-slate-900 text-[16px] font-bold">{{ total }}</text>
      <text x="50" y="62" text-anchor="middle" transform="rotate(90 50 50)" class="fill-slate-500 text-[6px]">{{ centerLabel }}</text>
    </svg>

    <ul class="w-full space-y-2">
      <li
        v-for="segment in segments"
        :key="segment.key"
        class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm transition"
        :class="hovered === segment.key ? 'bg-slate-100' : ''"
        @pointerenter="hovered = segment.key"
        @pointerleave="hovered = null"
      >
        <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ background: segment.color }"></span>
        <span class="flex-1 text-slate-700">{{ segment.label }}</span>
        <span class="font-mono font-semibold tabular-nums text-slate-900">{{ segment.value }}</span>
        <span class="w-10 text-right text-xs tabular-nums text-slate-500">{{ share(segment.value) }}%</span>
      </li>
    </ul>
  </div>
</template>
