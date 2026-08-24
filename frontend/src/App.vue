<script setup>
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import { useCatalogStore } from '@/stores/catalogs'

const auth = useAuthStore()
const branding = useBrandingStore()
const catalogs = useCatalogStore()
const route = useRoute()
const router = useRouter()

const year = new Date().getFullYear()

const PUBLIC_LINKS = [
  { name: 'public.services', label: 'Servicios' },
  { name: 'public.search', label: 'Consulta pública' },
  { name: 'public.contact', label: 'Contáctenos' },
]

// "Administración" no debe quedar activa en las otras secciones del panel.
const isExamsArea = computed(
  () => route.path.startsWith('/admin')
    && !['login', 'admin.branding', 'admin.messages'].includes(route.name),
)

async function handleLogout() {
  await auth.logout()
  catalogs.reset()
  router.push({ name: 'public.search' })
}
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div class="mx-auto flex min-h-16 max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2">
        <RouterLink :to="{ name: 'public.services' }" class="flex items-center gap-2.5">
          <img
            v-if="branding.logo"
            :src="branding.logo"
            alt=""
            class="h-9 w-9 shrink-0 object-contain"
          >
          <span
            v-else
            class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-700 text-white"
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
            <span class="block text-sm font-bold text-slate-900">{{ branding.appName }}</span>
            <span class="block text-xs text-slate-500">{{ branding.tagline }}</span>
          </span>
        </RouterLink>

        <nav class="flex flex-wrap items-center gap-1">
          <RouterLink
            v-for="link in PUBLIC_LINKS"
            :key="link.name"
            :to="{ name: link.name }"
            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
            active-class="bg-brand-50 text-brand-800"
          >
            {{ link.label }}
          </RouterLink>

          <template v-if="auth.isAuthenticated">
            <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:block" aria-hidden="true"></span>

            <RouterLink
              :to="{ name: 'admin.exams' }"
              class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
              :class="isExamsArea ? 'bg-brand-50 text-brand-800' : ''"
            >
              Administración
            </RouterLink>

            <RouterLink
              :to="{ name: 'admin.messages' }"
              class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
              active-class="bg-brand-50 text-brand-800"
            >
              Mensajes
            </RouterLink>

            <RouterLink
              :to="{ name: 'admin.branding' }"
              class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
              active-class="bg-brand-50 text-brand-800"
              title="Configuración"
            >
              <svg
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <circle cx="12" cy="12" r="3" />
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
              </svg>
              <span class="hidden lg:inline">Configuración</span>
            </RouterLink>

            <span class="mx-1 hidden text-sm text-slate-600 xl:inline">{{ auth.user?.name }}</span>

            <button type="button" class="btn-ghost" @click="handleLogout">Salir</button>
          </template>

          <RouterLink v-else :to="{ name: 'login' }" class="btn-primary">Ingresar</RouterLink>
        </nav>
      </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
      <RouterView />
    </main>

    <footer class="border-t border-slate-200 bg-white">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500">
        <p>© {{ year }} {{ branding.appName }} · {{ branding.tagline }}</p>

        <nav class="flex gap-4">
          <RouterLink :to="{ name: 'public.services' }" class="hover:text-brand-700">Servicios</RouterLink>
          <RouterLink :to="{ name: 'public.search' }" class="hover:text-brand-700">Consulta pública</RouterLink>
          <RouterLink :to="{ name: 'public.contact' }" class="hover:text-brand-700">Contáctenos</RouterLink>
        </nav>
      </div>
    </footer>
  </div>
</template>
