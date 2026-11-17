<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  text: { type: String, default: '' },
  /** Video de fondo; mientras carga (o sin video) se ve el póster. */
  video: { type: String, default: '' },
  poster: { type: String, default: '' },
  /** Nombres que desfilan en la franja inferior. */
  marquee: { type: Array, default: () => [] },
  marqueeLabel: { type: String, default: '' },
})

const reducedMotion =
  typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false
</script>

<template>
  <!-- El banner ocupa toda la pantalla, bajo el menú flotante. -->
  <div>
    <section class="relative isolate flex min-h-svh items-center overflow-hidden">
      <!-- Marco redondeado con un borde fino alrededor, como en el diseño de referencia. -->
      <div class="absolute inset-1 -z-10 overflow-hidden rounded-3xl bg-brand-900 lg:rounded-[3rem]">
        <video
          v-if="video && !reducedMotion"
          class="size-full object-cover opacity-70"
          :src="video"
          :poster="poster"
          autoplay
          loop
          muted
          playsinline
          aria-hidden="true"
        ></video>
        <img v-else-if="poster" :src="poster" alt="" class="size-full object-cover opacity-70">

        <!-- El velo toma el color de marca y garantiza contraste sobre cualquier cuadro. -->
        <div
          class="absolute inset-0 bg-gradient-to-r from-brand-900/90 via-brand-900/60 to-brand-900/10"
          aria-hidden="true"
        ></div>
      </div>

      <div class="mx-auto w-full max-w-7xl px-6 pt-32 pb-24 text-center lg:px-12 lg:text-left">
        <p v-if="eyebrow" class="text-xs font-semibold tracking-[0.2em] text-accent-300 uppercase">
          {{ eyebrow }}
        </p>

        <h1
          class="mx-auto mt-6 max-w-3xl text-5xl font-extrabold tracking-tight text-balance text-white md:text-6xl lg:mx-0 xl:text-7xl"
        >
          {{ title }}
        </h1>

        <p v-if="text" class="mx-auto mt-8 max-w-2xl text-lg text-balance text-white/80 lg:mx-0">
          {{ text }}
        </p>

        <div class="mt-12 flex flex-col items-center justify-center gap-2 sm:flex-row lg:justify-start">
          <a
            href="#servicios"
            class="inline-flex h-12 items-center rounded-full bg-white pr-3 pl-5 text-base font-bold text-brand-900 shadow-lg transition hover:bg-brand-50 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-900"
          >
            <span class="text-nowrap">Ver nuestros servicios</span>
            <svg class="ml-1 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="m9 18 6-6-6-6" />
            </svg>
          </a>

          <RouterLink
            :to="{ name: 'public.contact' }"
            class="inline-flex h-12 items-center rounded-full px-5 text-base font-bold text-white transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white"
          >
            <span class="text-nowrap">Agendar una cita</span>
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- Franja en movimiento: el contenido va duplicado para que el bucle no tenga corte. -->
    <section
      v-if="marquee.length"
      class="flex flex-col items-center px-4 sm:px-8 md:flex-row lg:px-12 2xl:px-20"
    >
      <p
        v-if="marqueeLabel"
        class="pt-4 text-sm text-slate-600 md:max-w-44 md:border-r md:border-slate-200 md:pt-0 md:pr-6 md:text-end"
      >
        {{ marqueeLabel }}
      </p>

      <div class="marquee relative w-full overflow-hidden py-6 md:w-[calc(100%-11rem)]">
        <ul class="marquee-track flex w-max gap-16" :style="reducedMotion ? { animation: 'none' } : null">
          <li
            v-for="(item, position) in [...marquee, ...marquee]"
            :key="position"
            class="text-sm font-semibold whitespace-nowrap text-brand-800"
            :aria-hidden="position >= marquee.length"
          >
            {{ item }}
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>

<style scoped>
.marquee {
  mask-image: linear-gradient(to right, transparent, black 5rem, black calc(100% - 5rem), transparent);
}

.marquee-track {
  animation: marquee 40s linear infinite;
}

/* Al pasar el cursor se frena en lugar de detenerse en seco. */
.marquee:hover .marquee-track {
  animation-duration: 80s;
}

@keyframes marquee {
  to {
    /* La mitad del ancho más la mitad del espacio entre elementos (gap-16 = 4rem). */
    transform: translateX(calc(-50% - 2rem));
  }
}
</style>
