<script setup>
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCatalogStore } from '@/stores/catalogs'

const auth = useAuthStore()
const catalogs = useCatalogStore()
const route = useRoute()
const router = useRouter()

const appName = import.meta.env.VITE_APP_NAME || 'Centro Médico Ocupacional'
const year = new Date().getFullYear()

const isAdminArea = computed(() => route.path.startsWith('/admin') && route.name !== 'login')

async function handleLogout() {
  await auth.logout()
  catalogs.reset()
  router.push({ name: 'public.search' })
}
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
        <RouterLink :to="{ name: 'public.search' }" class="flex items-center gap-2.5">
          <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-700 text-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" aria-hidden="true">
              <path d="M12 5v14M5 12h14" />
            </svg>
          </span>
          <span class="leading-tight">
            <span class="block text-sm font-bold text-slate-900">{{ appName }}</span>
            <span class="block text-xs text-slate-500">Exámenes médicos ocupacionales</span>
          </span>
        </RouterLink>

        <nav class="flex items-center gap-1">
          <RouterLink
            :to="{ name: 'public.search' }"
            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
            active-class="bg-brand-50 text-brand-800"
          >
            Consulta pública
          </RouterLink>

          <template v-if="auth.isAuthenticated">
            <RouterLink
              :to="{ name: 'admin.exams' }"
              class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
              :class="isAdminArea ? 'bg-brand-50 text-brand-800' : ''"
            >
              Administración
            </RouterLink>

            <span class="mx-2 hidden text-sm text-slate-400 sm:inline">|</span>
            <span class="hidden text-sm text-slate-600 sm:inline">{{ auth.user?.name }}</span>

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
      <div class="mx-auto max-w-6xl px-4 py-5 text-center text-xs text-slate-500">
        © {{ year }} {{ appName }} · Plataforma de generación y verificación de exámenes médicos ocupacionales.
      </div>
    </footer>
  </div>
</template>
