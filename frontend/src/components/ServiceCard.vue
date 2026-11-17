<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  /** Trazo de un ícono de 24×24 (Lucide) que se dibuja grande como decoración. */
  icon: { type: String, required: true },
  /** Destino del enlace de acción (lo que acepta RouterLink). */
  to: { type: [String, Object], required: true },
  cta: { type: String, default: 'Saber más' },
  /** Combinación de colores, siempre sobre la paleta de Configuración. */
  tone: { type: String, default: 'brand' },
})

const TONES = {
  brand: 'bg-brand-700 text-white',
  accent: 'bg-accent-600 text-white',
  soft: 'bg-brand-100 text-brand-900',
  light: 'bg-accent-100 text-brand-900',
  dark: 'bg-brand-900 text-white',
}
</script>

<template>
  <RouterLink
    :to="to"
    class="service-card group relative isolate flex min-h-60 flex-col overflow-hidden rounded-3xl p-7 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:outline-none"
    :class="TONES[tone] || TONES.brand"
  >
    <h3 class="max-w-[75%] text-2xl leading-tight font-extrabold tracking-tight text-balance">{{ title }}</h3>
    <p v-if="description" class="mt-3 max-w-[70%] text-sm leading-relaxed opacity-80">{{ description }}</p>

    <span class="mt-auto inline-flex items-center gap-2 pt-8 text-sm font-bold">
      {{ cta }}
      <span class="flex h-8 w-8 items-center justify-center rounded-full bg-current/15 transition duration-300 group-hover:translate-x-1">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M5 12h14M13 6l6 6-6 6" />
        </svg>
      </span>
    </span>

    <!-- Imagen decorativa: el ícono en grande, que se mueve al pasar el cursor. -->
    <svg
      class="decor pointer-events-none absolute -right-6 -bottom-6 -z-10 h-40 w-40 opacity-20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.25"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <path :d="icon" />
    </svg>
  </RouterLink>
</template>

<style scoped>
.decor {
  transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1), opacity 0.3s;
}

.service-card:hover .decor {
  transform: scale(1.12) rotate(-8deg) translate(-0.5rem, -0.5rem);
  opacity: 0.3;
}

@media (prefers-reduced-motion: reduce) {
  .decor,
  .service-card { transition: none; }

  .service-card:hover .decor { transform: none; }
}
</style>
