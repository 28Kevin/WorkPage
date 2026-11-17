<script setup>
defineProps({
  image: { type: String, required: true },
  title: { type: String, required: true },
  caption: { type: String, default: '' },
  /** Posición en la galería: decide el degradado, para que la retícula no quede de un solo color. */
  index: { type: Number, default: 0 },
})

// Cada par mezcla la paleta de Configuración con un tono complementario.
const TONES = [
  ['var(--color-brand-500)', 'var(--color-accent-400)'],
  ['var(--color-accent-500)', '#14b8a6'],
  ['#6366f1', 'var(--color-brand-500)'],
  ['#f43f5e', '#f59e0b'],
  ['#10b981', 'var(--color-accent-500)'],
  ['#a855f7', '#ec4899'],
]

/** Foco de luz que sigue al cursor (solo con mouse). */
function follow(event) {
  if (event.pointerType !== 'mouse') return

  const card = event.currentTarget
  const box = card.getBoundingClientRect()

  card.style.setProperty('--mx', `${event.clientX - box.left}px`)
  card.style.setProperty('--my', `${event.clientY - box.top}px`)
}
</script>

<template>
  <figure
    class="highlight group relative rounded-3xl p-px"
    :style="{ '--from': TONES[index % TONES.length][0], '--to': TONES[index % TONES.length][1] }"
    @pointermove="follow"
  >
    <div class="relative h-full overflow-hidden rounded-[calc(1.5rem-1px)] bg-white">
      <div class="overflow-hidden">
        <img
          :src="image"
          :alt="title"
          loading="lazy"
          class="aspect-[4/3] w-full object-cover transition duration-700 group-hover:scale-105"
        >
      </div>

      <!-- Velo de color que se intensifica al pasar el cursor. -->
      <div class="tint pointer-events-none absolute inset-x-0 top-0 aspect-[4/3]" aria-hidden="true"></div>

      <figcaption class="relative px-5 py-4">
        <span class="mb-2 block h-1 w-10 rounded-full transition-all duration-500 group-hover:w-20" style="background: linear-gradient(to right, var(--from), var(--to))" aria-hidden="true"></span>
        <p class="text-sm font-bold text-slate-900">{{ title }}</p>
        <p v-if="caption" class="mt-1 text-xs leading-relaxed text-slate-500">{{ caption }}</p>
      </figcaption>

      <span class="spotlight pointer-events-none absolute inset-0" aria-hidden="true"></span>
    </div>
  </figure>
</template>

<style scoped>
/* El padding de 1px deja ver este fondo como borde con degradado. */
.highlight {
  background: linear-gradient(135deg, var(--from), transparent 45%, transparent 55%, var(--to));
  box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
  transition: transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1), box-shadow 0.4s;
}

.highlight:hover {
  transform: translateY(-4px);
  background: linear-gradient(135deg, var(--from), var(--to));
  box-shadow: 0 20px 40px -18px var(--from);
}

.tint {
  background: linear-gradient(to top, color-mix(in srgb, var(--from) 45%, transparent), transparent 60%);
  opacity: 0.35;
  transition: opacity 0.4s;
}

.highlight:hover .tint { opacity: 0.8; }

.spotlight {
  background: radial-gradient(260px circle at var(--mx, 50%) var(--my, 50%), rgb(255 255 255 / 0.35), transparent 60%);
  opacity: 0;
  transition: opacity 0.3s;
}

.highlight:hover .spotlight { opacity: 1; }

@media (prefers-reduced-motion: reduce) {
  .highlight,
  .highlight img { transition: none; }

  .highlight:hover { transform: none; }
}
</style>
