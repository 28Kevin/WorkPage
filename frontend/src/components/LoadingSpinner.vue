<script setup>
defineProps({
  label: { type: String, default: 'Cargando…' },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <div :class="['flex items-center justify-center gap-2.5 text-slate-500', compact ? 'py-3' : 'py-12']">
    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
      <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z" />
    </svg>
    <span class="text-sm">{{ label }}</span>
  </div>
</template>
