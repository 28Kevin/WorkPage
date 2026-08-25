<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

defineProps({
  services: { type: Array, required: true },
})

const track = ref(null)
const atStart = ref(true)
const atEnd = ref(false)

/** Habilita o deshabilita las flechas según dónde esté el desplazamiento. */
function sync() {
  const element = track.value

  if (!element) return

  const max = element.scrollWidth - element.clientWidth

  atStart.value = element.scrollLeft <= 4
  atEnd.value = element.scrollLeft >= max - 4
}

/** Avanza o retrocede el ancho de una tarjeta. */
function scrollBy(direction) {
  const element = track.value

  if (!element) return

  const card = element.firstElementChild

  element.scrollBy({
    left: direction * ((card?.clientWidth ?? 300) + 16),
    behavior: 'smooth',
  })
}

onMounted(() => {
  sync()
  window.addEventListener('resize', sync)
})

onBeforeUnmount(() => window.removeEventListener('resize', sync))
</script>

<template>
  <div class="relative">
    <div
      ref="track"
      class="no-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2"
      tabindex="0"
      aria-label="Servicios que ofrecemos"
      @scroll="sync"
    >
      <article
        v-for="(service, position) in services"
        :key="service.title"
        class="card flex w-[280px] shrink-0 snap-start flex-col bg-white p-6 sm:w-[300px] lg:w-[264px] xl:w-[280px]"
      >
        <span
          class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-100 text-sm font-bold text-brand-800"
          aria-hidden="true"
        >
          {{ String(position + 1).padStart(2, '0') }}
        </span>

        <h3 class="mt-4 text-base leading-snug font-bold text-balance text-slate-900">
          {{ service.title }}
        </h3>

        <p class="mt-2 text-sm font-medium text-brand-800">{{ service.summary }}</p>
        <p class="mt-3 line-clamp-5 text-sm leading-relaxed text-slate-600">{{ service.detail }}</p>

        <ul class="mt-auto flex flex-wrap gap-1.5 pt-4">
          <li v-for="item in service.includes" :key="item" class="chip">{{ item }}</li>
        </ul>
      </article>
    </div>

    <div class="mt-6 flex justify-center gap-3">
      <button
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 disabled:opacity-40 focus-visible:ring-2 focus-visible:ring-brand-500"
        aria-label="Ver servicios anteriores"
        :disabled="atStart"
        @click="scrollBy(-1)"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m15 18-6-6 6-6" />
        </svg>
      </button>

      <button
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 disabled:opacity-40 focus-visible:ring-2 focus-visible:ring-brand-500"
        aria-label="Ver más servicios"
        :disabled="atEnd"
        @click="scrollBy(1)"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m9 18 6-6-6-6" />
        </svg>
      </button>
    </div>
  </div>
</template>
