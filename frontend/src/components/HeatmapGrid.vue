<script setup>
import { computed, ref } from 'vue'

/*
 * Mapa de calor día × hora. Magnitud = un solo tono, de claro a oscuro
 * (rampa secuencial azul). El cero va en gris neutro para que "nada" no se
 * confunda con "poco".
 */
const props = defineProps({
  /** 7 filas (lunes a domingo) × 24 columnas. */
  cells: { type: Array, required: true },
  weekdays: { type: Array, required: true },
  max: { type: Number, default: 0 },
  unit: { type: String, default: 'documento(s)' },
})

const RAMP = ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b']
const EMPTY = '#f1f5f9'

function color(count) {
  if (!count || !props.max) return EMPTY

  const step = Math.min(RAMP.length - 1, Math.floor((count / props.max) * RAMP.length))

  return RAMP[step]
}

const hovered = ref(null)

const tooltip = computed(() => {
  if (!hovered.value) return null

  const { day, hour } = hovered.value

  return `${props.weekdays[day]} ${String(hour).padStart(2, '0')}:00 · ${props.cells[day][hour]} ${props.unit}`
})
</script>

<template>
  <div>
    <div class="overflow-x-auto">
      <table class="w-full min-w-[40rem] border-separate border-spacing-[2px]" aria-label="Documentos por día y hora">
        <thead>
          <tr>
            <th class="w-10"></th>
            <!-- Una etiqueta cada 3 horas para no amontonar. -->
            <th
              v-for="hour in 24"
              :key="hour"
              scope="col"
              class="text-[10px] font-normal text-slate-500"
            >
              {{ (hour - 1) % 3 === 0 ? String(hour - 1).padStart(2, '0') : '' }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, day) in cells" :key="day">
            <th scope="row" class="pr-2 text-left text-xs font-medium text-slate-600">{{ weekdays[day] }}</th>
            <td
              v-for="(count, hour) in row"
              :key="hour"
              class="h-6 rounded-[4px] transition-shadow hover:ring-2 hover:ring-slate-900/40"
              :style="{ background: color(count) }"
              :aria-label="`${weekdays[day]} ${hour}:00, ${count} ${unit}`"
              @pointerenter="hovered = { day, hour }"
              @pointerleave="hovered = null"
            ></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
      <p class="min-h-4 font-medium text-slate-700" aria-live="polite">{{ tooltip }}</p>

      <!-- Escala: de cero al máximo del rango. -->
      <div class="flex items-center gap-1.5">
        <span>0</span>
        <span class="h-2.5 w-4 rounded-sm" :style="{ background: EMPTY }"></span>
        <span v-for="step in RAMP" :key="step" class="h-2.5 w-4 rounded-sm" :style="{ background: step }"></span>
        <span>{{ max }}</span>
      </div>
    </div>
  </div>
</template>
