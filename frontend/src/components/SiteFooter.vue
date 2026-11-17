<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const auth = useAuthStore()
const branding = useBrandingStore()

const year = new Date().getFullYear()

const LINKS = [
  { name: 'public.services', label: 'Servicios' },
  { name: 'public.search', label: 'Consulta pública' },
  { name: 'public.contact', label: 'Contáctenos' },
]

const schedule = computed(() =>
  (branding.center.schedule || '').split('\n').map((line) => line.trim()).filter(Boolean),
)

const contact = computed(() =>
  [branding.center.address, branding.center.phone, branding.center.email].filter(Boolean),
)

// Las columnas entran escalonadas la primera vez que el pie aparece en pantalla.
const root = ref(null)
const visible = ref(false)

let observer = null

onMounted(() => {
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

  if (reduced || !('IntersectionObserver' in window)) {
    visible.value = true
    return
  }

  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) {
      visible.value = true
      observer.disconnect()
    }
  }, { threshold: 0.1 })

  observer.observe(root.value)
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <footer
    ref="root"
    class="relative isolate mx-2 mt-8 overflow-hidden rounded-t-[2.5rem] border-x border-t border-slate-200 bg-white"
    :class="{ 'is-visible': visible }"
  >
    <!-- Resplandor del color de marca sobre el borde superior. -->
    <div class="glow" aria-hidden="true"></div>

    <div class="mx-auto grid max-w-7xl gap-12 px-6 py-14 lg:grid-cols-[1.2fr_2fr] lg:px-12">
      <div class="reveal" style="--delay: 0ms">
        <RouterLink :to="{ name: 'public.services' }" class="inline-flex items-center gap-2.5">
          <img v-if="branding.logo" :src="branding.logo" alt="" class="h-9 w-9 object-contain">
          <span class="text-base font-bold text-slate-900">{{ branding.appName }}</span>
        </RouterLink>
        <p class="mt-3 max-w-sm text-sm text-slate-600">{{ branding.tagline }}</p>
        <p v-if="branding.center.nit" class="mt-1 text-sm text-slate-500">NIT {{ branding.center.nit }}</p>

        <p class="mt-8 hidden text-xs text-slate-500 lg:block">
          © {{ year }} {{ branding.appName }}. Todos los derechos reservados.
        </p>
      </div>

      <div class="grid grid-cols-1 gap-10 sm:grid-cols-3">
        <div class="reveal" style="--delay: 100ms">
          <h3 class="text-xs font-semibold tracking-[0.2em] text-slate-900 uppercase">Navegación</h3>
          <ul class="mt-4 space-y-2.5 text-sm">
            <li v-for="link in LINKS" :key="link.name">
              <RouterLink :to="{ name: link.name }" class="text-slate-600 transition hover:text-brand-700">
                {{ link.label }}
              </RouterLink>
            </li>
            <li v-if="!auth.isAuthenticated">
              <RouterLink :to="{ name: 'login' }" class="text-slate-600 transition hover:text-brand-700">
                Ingreso administrativo
              </RouterLink>
            </li>
          </ul>
        </div>

        <div class="reveal" style="--delay: 200ms">
          <h3 class="text-xs font-semibold tracking-[0.2em] text-slate-900 uppercase">Dónde estamos</h3>
          <ul v-if="contact.length" class="mt-4 space-y-2.5 text-sm text-slate-600">
            <li v-for="line in contact" :key="line" class="break-words">{{ line }}</li>
          </ul>
          <p v-else class="mt-4 text-sm text-slate-600">—</p>
        </div>

        <div class="reveal" style="--delay: 300ms">
          <h3 class="text-xs font-semibold tracking-[0.2em] text-slate-900 uppercase">Horario</h3>
          <ul v-if="schedule.length" class="mt-4 space-y-2.5 text-sm text-slate-600">
            <li v-for="line in schedule" :key="line">{{ line }}</li>
          </ul>
          <p v-else class="mt-4 text-sm text-slate-600">Consúltenos el horario de atención.</p>
        </div>
      </div>
    </div>

    <p class="reveal border-t border-slate-100 px-6 py-5 text-center text-xs text-slate-500 lg:hidden" style="--delay: 400ms">
      © {{ year }} {{ branding.appName }}. Todos los derechos reservados.
    </p>
  </footer>
</template>

<style scoped>
.glow {
  position: absolute;
  top: 0;
  left: 50%;
  z-index: -1;
  width: min(60rem, 100%);
  height: 12rem;
  transform: translate(-50%, -60%);
  border-radius: 999px;
  background: radial-gradient(ellipse at center, color-mix(in srgb, var(--color-brand-400) 45%, transparent), transparent 70%);
  filter: blur(40px);
}

/* Línea luminosa sobre el borde, como en el diseño de referencia. */
footer::before {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  width: 40%;
  height: 1px;
  transform: translateX(-50%);
  background: linear-gradient(to right, transparent, var(--color-brand-500), transparent);
}

.reveal {
  opacity: 0;
  filter: blur(4px);
  transform: translateY(-8px);
  transition:
    opacity 0.8s ease var(--delay),
    filter 0.8s ease var(--delay),
    transform 0.8s ease var(--delay);
}

.is-visible .reveal {
  opacity: 1;
  filter: none;
  transform: none;
}
</style>
