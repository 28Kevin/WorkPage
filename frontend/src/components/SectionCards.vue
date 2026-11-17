<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

defineProps({
  /** [{ target, title, description, icon }]: 'icon' es el trazo de un SVG de 24×24. */
  items: { type: Array, required: true },
})

const reducedMotion =
  typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false

// Aparece con un leve desenfoque la primera vez que entra en pantalla.
const root = ref(null)
const visible = ref(reducedMotion)

let observer = null

onMounted(() => {
  if (visible.value || !('IntersectionObserver' in window)) {
    visible.value = true
    return
  }

  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) {
      visible.value = true
      observer.disconnect()
    }
  })

  observer.observe(root.value)
})

onBeforeUnmount(() => observer?.disconnect())

function go(target) {
  document.getElementById(target)?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' })
}
</script>

<template>
  <nav
    ref="root"
    aria-label="Secciones de la configuración"
    class="grid grid-cols-1 divide-x divide-y divide-dashed divide-slate-300 overflow-hidden rounded-xl border border-dashed border-slate-300 bg-white transition duration-700 sm:grid-cols-2 md:grid-cols-3"
    :class="visible ? 'opacity-100 blur-0' : '-translate-y-2 opacity-0 blur-[4px]'"
  >
    <!-- Los botones van con type="button": la vista entera es un formulario. -->
    <button
      v-for="item in items"
      :key="item.target"
      type="button"
      class="feature-card group relative overflow-hidden p-6 text-left transition hover:bg-brand-50/60 focus-visible:bg-brand-50 focus-visible:outline-none"
      @click="go(item.target)"
    >
      <!-- Retícula tenue en la esquina, como en el diseño de referencia. -->
      <span class="feature-grid pointer-events-none absolute inset-0" aria-hidden="true"></span>

      <svg
        class="relative h-6 w-6 text-brand-700 transition group-hover:scale-110"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path :d="item.icon" />
      </svg>

      <h3 class="relative mt-10 text-sm font-semibold text-slate-900 md:text-base">{{ item.title }}</h3>
      <p class="relative mt-2 text-xs font-light text-slate-600">{{ item.description }}</p>
    </button>
  </nav>
</template>

<style scoped>
.feature-grid {
  background-image:
    linear-gradient(to right, var(--color-brand-200) 1px, transparent 1px),
    linear-gradient(to bottom, var(--color-brand-200) 1px, transparent 1px);
  background-size: 20px 20px;
  mask-image: radial-gradient(circle at top right, black, transparent 65%);
  opacity: 0.6;
  transition: opacity 0.3s;
}

.feature-card:hover .feature-grid {
  opacity: 1;
}
</style>
