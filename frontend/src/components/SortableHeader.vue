<script setup>
import { computed } from 'vue'

/** Cabecera de tabla que ordena por su columna al pulsarla. */
const props = defineProps({
  field: { type: String, required: true },
  label: { type: String, required: true },
  /** Campo y dirección activos en el listado. */
  sort: { type: String, default: null },
  direction: { type: String, default: 'desc' },
  /** Dirección del primer clic: 'asc' para textos, 'desc' para fechas y números. */
  first: { type: String, default: 'desc' },
  align: { type: String, default: 'left' },
})

const emit = defineEmits(['sort'])

const active = computed(() => props.sort === props.field)

/** Lo leen los lectores de pantalla: el orden no queda solo en la flecha. */
const ariaSort = computed(() => {
  if (!active.value) return 'none'

  return props.direction === 'asc' ? 'ascending' : 'descending'
})
</script>

<template>
  <th
    scope="col"
    class="px-4 py-3 font-semibold"
    :class="align === 'right' ? 'text-right' : 'text-left'"
    :aria-sort="ariaSort"
  >
    <button
      type="button"
      class="group inline-flex items-center gap-1 transition hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none"
      :class="[active ? 'text-slate-900' : '', align === 'right' ? 'flex-row-reverse' : '']"
      :title="`Ordenar por ${label}`"
      @click="emit('sort', field, first)"
    >
      {{ label }}

      <svg
        class="h-3.5 w-3.5 shrink-0 transition"
        :class="active ? 'opacity-100' : 'opacity-0 group-hover:opacity-40'"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path :d="active && direction === 'asc' ? 'm6 15 6-6 6 6' : 'm6 9 6 6 6-6'" />
      </svg>
    </button>
  </th>
</template>
