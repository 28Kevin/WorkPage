<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'info',
    validator: (value) => ['info', 'success', 'error', 'warning'].includes(value),
  },
  title: { type: String, default: null },
})

const styles = {
  info: 'border-sky-200 bg-sky-50 text-sky-900',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  error: 'border-red-200 bg-red-50 text-red-900',
  warning: 'border-amber-200 bg-amber-50 text-amber-900',
}

const icons = {
  info: 'M12 9h.01M11 12h1v4h1',
  success: 'm9 12 2 2 4-4',
  error: 'M12 8v4m0 4h.01',
  warning: 'M12 9v4m0 4h.01',
}

const classes = computed(() => styles[props.variant])
const icon = computed(() => icons[props.variant])
</script>

<template>
  <div :class="['flex gap-3 rounded-lg border p-3.5 text-sm', classes]" role="alert">
    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="9" />
      <path :d="icon" />
    </svg>

    <div class="min-w-0 flex-1">
      <p v-if="title" class="font-semibold">{{ title }}</p>
      <div :class="title ? 'mt-0.5' : ''"><slot /></div>
    </div>
  </div>
</template>
