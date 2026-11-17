<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import DotMap from '@/components/DotMap.vue'
import { parseApiError } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const auth = useAuthStore()
const branding = useBrandingStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const message = ref(null)
const showPassword = ref(false)

async function submit() {
  errors.value = {}
  message.value = null

  try {
    await auth.login({ ...form })
    router.push(route.query.redirect || { name: 'admin.exams' })
  } catch (error) {
    const parsed = parseApiError(error)
    errors.value = parsed.errors
    message.value = parsed.message
  }
}
</script>

<template>
  <div class="flex min-h-[calc(100svh-9rem)] items-stretch py-2">
    <div class="enter grid w-full overflow-hidden rounded-3xl bg-white shadow-xl ring-1 shadow-slate-200/70 ring-slate-200 md:grid-cols-2 lg:grid-cols-[1.2fr_1fr]">
      <!-- ---------------------------------------------------------- mapa -->
      <div class="relative hidden flex-col justify-between bg-gradient-to-br from-brand-50 via-white to-accent-50 p-10 md:flex lg:p-14">
        <div class="flex items-center gap-2.5">
          <img v-if="branding.logo" :src="branding.logo" alt="" class="h-9 w-9 object-contain">
          <span class="text-sm font-bold text-brand-900">{{ branding.appName }}</span>
        </div>

        <DotMap class="my-6" />

        <div>
          <h2 class="text-3xl font-bold tracking-tight text-slate-900 lg:text-4xl">Certificados que llegan a todas partes</h2>
          <p class="mt-3 max-w-lg text-base leading-relaxed text-slate-600">
            Cada documento emitido se puede verificar en línea con su código QR, desde cualquier lugar.
          </p>
        </div>
      </div>

      <!-- ---------------------------------------------------- formulario -->
      <div class="flex flex-col justify-center p-8 sm:p-12 lg:p-16">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 lg:text-4xl">Bienvenido de nuevo</h1>
        <p class="mt-2 text-sm text-slate-600">Ingrese sus credenciales para acceder al módulo administrativo.</p>

        <form class="mx-auto mt-8 w-full max-w-md md:mx-0" novalidate @submit.prevent="submit">
          <AlertMessage v-if="message" variant="error" class="mb-5">{{ message }}</AlertMessage>
          <AlertMessage v-else-if="route.query.expired" variant="info" class="mb-5">
            Su sesión se cerró tras 15 minutos sin actividad. Ingrese de nuevo para continuar.
          </AlertMessage>

          <div class="space-y-5">
            <div>
              <label for="login-email" class="mb-1.5 block text-sm font-medium text-slate-700">
                Correo electrónico <span class="text-red-600">*</span>
              </label>
              <input
                id="login-email"
                v-model="form.email"
                type="email"
                autocomplete="username"
                class="field-input"
                :class="{ 'field-input-error': errors.email }"
                placeholder="admin@centromedico.test"
                :aria-invalid="Boolean(errors.email)"
                aria-describedby="login-email-error"
                required
              >
              <p v-if="errors.email" id="login-email-error" class="mt-1.5 text-xs text-red-600">{{ errors.email[0] }}</p>
            </div>

            <div>
              <label for="login-password" class="mb-1.5 block text-sm font-medium text-slate-700">
                Contraseña <span class="text-red-600">*</span>
              </label>
              <div class="relative">
                <input
                  id="login-password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  class="field-input pr-11"
                  :class="{ 'field-input-error': errors.password }"
                  placeholder="••••••••"
                  :aria-invalid="Boolean(errors.password)"
                  aria-describedby="login-password-error"
                  required
                >
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 transition hover:text-slate-700 focus-visible:text-slate-700 focus-visible:outline-none"
                  :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                  :aria-pressed="showPassword"
                  @click="showPassword = !showPassword"
                >
                  <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                    <circle cx="12" cy="12" r="3" />
                    <path v-if="showPassword" d="M3 3l18 18" />
                  </svg>
                </button>
              </div>
              <p v-if="errors.password" id="login-password-error" class="mt-1.5 text-xs text-red-600">{{ errors.password[0] }}</p>
            </div>
          </div>

          <button
            type="submit"
            class="group mt-8 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-600 to-accent-600 font-bold text-white shadow-lg shadow-brand-600/25 transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:opacity-70"
            :disabled="auth.loading"
          >
            <svg v-if="auth.loading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
              <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            {{ auth.loading ? 'Verificando…' : 'Ingresar' }}
            <svg v-if="!auth.loading" class="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
          ¿Solo necesita validar un examen?
          <RouterLink :to="{ name: 'public.search' }" class="font-semibold text-brand-700 hover:underline">
            Use la consulta pública
          </RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.enter {
  animation: enter 0.6s cubic-bezier(0.2, 0.7, 0.2, 1) both;
}

@keyframes enter {
  from { opacity: 0; transform: translateY(12px) scale(0.98); }
}

@media (prefers-reduced-motion: reduce) {
  .enter { animation: none; }
}
</style>
