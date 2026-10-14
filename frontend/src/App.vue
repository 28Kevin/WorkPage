<script setup>
import { RouterLink, RouterView, useRouter } from 'vue-router'
import AdminMenu from '@/components/AdminMenu.vue'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import { useCatalogStore } from '@/stores/catalogs'

const auth = useAuthStore()
const branding = useBrandingStore()
const catalogs = useCatalogStore()
const router = useRouter()

const year = new Date().getFullYear()

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
    <header class="sticky top-0 z-30 border-b border-white/10 bg-brand-800/95 text-white backdrop-blur">
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

        <nav class="flex flex-wrap items-center gap-1">
          <RouterLink
            v-for="link in PUBLIC_LINKS"
            :key="link.name"
            :to="{ name: link.name }"
            class="rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10"
            active-class="bg-white/15 text-white"
          >
            {{ link.label }}
          </RouterLink>

          <template v-if="auth.isAuthenticated">
            <span class="mx-1 hidden h-5 w-px bg-white/25 sm:block" aria-hidden="true"></span>

            <AdminMenu @logout="handleLogout" />
          </template>

          <RouterLink
            v-else
            :to="{ name: 'login' }"
            class="rounded-lg bg-white px-4 py-2 text-sm font-bold text-brand-800 shadow-sm transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-800"
          >
            Ingresar
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
      <RouterView />
    </main>

    <footer class="bg-brand-900 text-white">
      <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-3">
        <div class="sm:col-span-2">
          <p class="text-base font-bold">{{ branding.appName }}</p>
          <p class="mt-1 text-sm text-white/70">{{ branding.tagline }}</p>
          <p v-if="branding.center.nit" class="mt-1 text-sm text-white/70">
            NIT {{ branding.center.nit }}
          </p>

          <p v-if="branding.center.address" class="mt-4 text-sm text-white/70">
            {{ branding.center.address }}
          </p>
          <p v-if="branding.center.phone" class="text-sm text-white/70">{{ branding.center.phone }}</p>
          <p v-if="branding.center.email" class="text-sm text-white/70">{{ branding.center.email }}</p>
        </div>

        <nav class="flex flex-col gap-2 text-sm sm:items-end">
          <RouterLink :to="{ name: 'public.services' }" class="text-white/80 transition hover:text-white">
            Servicios
          </RouterLink>
          <RouterLink :to="{ name: 'public.search' }" class="text-white/80 transition hover:text-white">
            Consulta pública
          </RouterLink>
          <RouterLink :to="{ name: 'public.contact' }" class="text-white/80 transition hover:text-white">
            Contáctenos
          </RouterLink>
        </nav>
      </div>

      <div class="border-t border-white/10">
        <p class="mx-auto max-w-6xl px-4 py-4 text-center text-xs text-white/60">
          © {{ year }} {{ branding.appName }} · Plataforma de generación y verificación de exámenes médicos
          ocupacionales.
        </p>
      </div>
    </footer>
  </div>
</template>
