<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import AdminMenu from '@/components/AdminMenu.vue'
import GlobalLoader from '@/components/GlobalLoader.vue'
import SiteFooter from '@/components/SiteFooter.vue'
import { useIdleSession } from '@/composables/useIdleSession'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import { useCatalogStore } from '@/stores/catalogs'

const auth = useAuthStore()
const branding = useBrandingStore()
const catalogs = useCatalogStore()
const router = useRouter()
const route = useRoute()

// La sesión del panel se cierra tras 15 minutos sin actividad.
useIdleSession()

/** Páginas a pantalla completa (inicio, login): su contenido va por debajo del menú flotante. */
const isHome = computed(() => Boolean(route.meta.fullBleed))

// El menú es transparente sobre el video y se vuelve sólido al hacer scroll.
const scrolled = ref(false)
const menuOpen = ref(false)

function onScroll() {
  scrolled.value = window.scrollY > 40
}

watch(() => route.fullPath, () => {
  menuOpen.value = false
})

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

const solidHeader = computed(() => scrolled.value || menuOpen.value || !isHome.value)

const PUBLIC_LINKS = [
  { name: 'public.services', label: 'Servicios' },
  { name: 'public.search', label: 'Consulta pública' },
  { name: 'public.contact', label: 'Contáctenos' },
]

async function handleLogout() {
  await auth.logout()
  catalogs.reset()
  router.push({ name: 'public.search' })
}
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <!-- Menú flotante: transparente sobre el video de inicio, con fondo de marca al hacer scroll. -->
    <header class="fixed inset-x-0 top-0 z-30 px-2 pt-2 text-white">
      <div
        class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-3xl px-4 transition-all duration-300 lg:px-8"
        :class="solidHeader ? 'bg-brand-800/80 py-2 shadow-lg backdrop-blur-2xl' : 'py-4'"
      >
        <RouterLink :to="{ name: 'public.services' }" class="flex items-center gap-2.5">
          <img
            v-if="branding.logo"
            :src="branding.logo"
            alt=""
            class="h-9 w-9 shrink-0 object-contain"
          >
          <span
            v-else
            class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15 text-white"
          >
            <svg
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              aria-hidden="true"
            >
              <path d="M12 5v14M5 12h14" />
            </svg>
          </span>

          <span class="leading-tight">
            <span class="block text-sm font-bold text-white">{{ branding.appName }}</span>
            <span class="block text-xs text-white/70">{{ branding.tagline }}</span>
          </span>
        </RouterLink>

        <button
          type="button"
          class="relative -mr-2 flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-white/10 lg:hidden"
          :aria-label="menuOpen ? 'Cerrar menú' : 'Abrir menú'"
          :aria-expanded="menuOpen"
          aria-controls="main-menu"
          @click="menuOpen = !menuOpen"
        >
          <!-- Hamburguesa y X se cruzan girando, como en el diseño de referencia. -->
          <svg class="absolute h-6 w-6 transition duration-200"
               :class="menuOpen ? 'scale-0 rotate-180 opacity-0' : ''"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
               aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16" />
          </svg>
          <svg class="absolute h-6 w-6 transition duration-200"
               :class="menuOpen ? '' : 'scale-0 -rotate-180 opacity-0'"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
               aria-hidden="true">
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        </button>

        <nav
          id="main-menu"
          class="w-full flex-col gap-1 pb-4 lg:flex lg:w-auto lg:flex-row lg:items-center lg:pb-0"
          :class="menuOpen ? 'flex' : 'hidden'"
        >
          <RouterLink
            v-for="link in PUBLIC_LINKS"
            :key="link.name"
            :to="{ name: link.name }"
            class="rounded-full px-4 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white"
            active-class="bg-white/15 text-white"
          >
            {{ link.label }}
          </RouterLink>

          <template v-if="auth.isAuthenticated">
            <span class="mx-1 hidden h-5 w-px bg-white/25 lg:block" aria-hidden="true"></span>

            <AdminMenu @logout="handleLogout" />
          </template>

          <RouterLink
            v-else
            :to="{ name: 'login' }"
            class="mt-2 rounded-full bg-white px-5 py-2 text-center text-sm font-bold text-brand-800 shadow-sm transition lg:mt-0 lg:ml-2 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-800"
          >
            Ingresar
          </RouterLink>
        </nav>
      </div>
    </header>

    <!-- Con el menú fijo, las demás páginas dejan espacio arriba; la de inicio va por debajo. -->
    <!-- Inicio y login usan todo el ancho; el resto conserva la columna central. -->
    <main
      class="mx-auto w-full flex-1 pb-8"
      :class="[
        isHome ? '' : 'pt-28',
        isHome || route.meta.wide ? 'px-0' : 'max-w-6xl px-4',
        route.meta.wide ? 'px-4 sm:px-8 lg:px-12' : '',
      ]"
    >
      <RouterView />
    </main>

    <SiteFooter />

    <GlobalLoader />
  </div>
</template>
