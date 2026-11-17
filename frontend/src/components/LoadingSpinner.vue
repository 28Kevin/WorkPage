<script setup>
defineProps({
  label: { type: String, default: 'Cargando…' },
  /** Texto secundario bajo el título; vacío lo oculta. */
  hint: { type: String, default: 'Esto tomará solo un momento' },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <div
    :class="['flex flex-col items-center justify-center text-center', compact ? 'gap-2 py-3' : 'gap-5 py-12']"
    role="status"
    aria-live="polite"
  >
    <!-- Anillos concéntricos que giran en sentidos opuestos, con el color de marca. -->
    <div class="loader" :class="{ 'loader-sm': compact }" aria-hidden="true">
      <span class="ring ring-outer"></span>
      <span class="ring ring-middle"></span>
      <span class="ring ring-inner"></span>
      <span class="core"></span>
    </div>

    <div v-if="!compact">
      <p class="text-base font-semibold text-slate-900">{{ label }}</p>
      <p v-if="hint" class="shimmer mt-1 text-sm">{{ hint }}</p>
    </div>
    <span v-else class="text-sm text-slate-500">{{ label }}</span>
  </div>
</template>

<style scoped>
.loader {
  position: relative;
  width: 4rem;
  height: 4rem;
}

.loader-sm {
  width: 1.75rem;
  height: 1.75rem;
}

.ring {
  position: absolute;
  border-radius: 999px;
  border: 3px solid transparent;
}

.loader-sm .ring { border-width: 2px; }

.ring-outer {
  inset: 0;
  border-top-color: var(--color-brand-600);
  border-right-color: var(--color-brand-600);
  animation: spin 1.1s cubic-bezier(0.5, 0.1, 0.5, 0.9) infinite;
}

.ring-middle {
  inset: 18%;
  border-bottom-color: var(--color-accent-500);
  border-left-color: var(--color-accent-500);
  animation: spin 1.4s cubic-bezier(0.5, 0.1, 0.5, 0.9) infinite reverse;
}

.ring-inner {
  inset: 34%;
  border-top-color: var(--color-brand-400);
  animation: spin 0.8s linear infinite;
}

.core {
  position: absolute;
  inset: 44%;
  border-radius: 999px;
  background: var(--color-brand-600);
  animation: pulse 1.4s ease-in-out infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes pulse {
  50% { transform: scale(1.6); opacity: 0.5; }
}

/* Brillo que recorre el texto secundario. */
.shimmer {
  background: linear-gradient(90deg, var(--color-slate-400) 0%, var(--color-slate-700) 50%, var(--color-slate-400) 100%);
  background-size: 200% 100%;
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  animation: shimmer 2s linear infinite;
}

@keyframes shimmer {
  from { background-position: 200% 0; }
  to { background-position: -200% 0; }
}

@media (prefers-reduced-motion: reduce) {
  .ring { animation-duration: 3s; }
  .core, .shimmer { animation: none; }
  .shimmer { color: var(--color-slate-500); }
}
</style>
