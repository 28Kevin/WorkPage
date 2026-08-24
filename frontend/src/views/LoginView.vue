<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import { parseApiError } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const message = ref(null)

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
  <div class="mx-auto max-w-md">
    <div class="mb-6 text-center">
      <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-white">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="4" y="10" width="16" height="11" rx="2" />
          <path d="M8 10V7a4 4 0 1 1 8 0v3" />
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Módulo administrativo</h1>
      <p class="mt-1 text-sm text-slate-600">Acceso restringido. Ingrese sus credenciales para continuar.</p>
    </div>

    <form class="card p-6" novalidate @submit.prevent="submit">
      <AlertMessage v-if="message" variant="error" class="mb-5">{{ message }}</AlertMessage>

      <div class="space-y-4">
        <FormField v-slot="{ id, hasError }" label="Correo electrónico" :error="errors.email" required>
          <input
            :id="id"
            v-model="form.email"
            type="email"
            autocomplete="username"
            class="field-input"
            :class="{ 'field-input-error': hasError }"
            placeholder="admin@centromedico.test"
            required
          />
        </FormField>

        <FormField v-slot="{ id, hasError }" label="Contraseña" :error="errors.password" required>
          <input
            :id="id"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            class="field-input"
            :class="{ 'field-input-error': hasError }"
            placeholder="••••••••"
            required
          />
        </FormField>
      </div>

      <button type="submit" class="btn-primary mt-6 w-full" :disabled="auth.loading">
        {{ auth.loading ? 'Verificando…' : 'Ingresar' }}
      </button>
    </form>

    <p class="mt-4 text-center text-xs text-slate-500">
      ¿Solo necesita validar un examen?
      <RouterLink :to="{ name: 'public.search' }" class="font-semibold text-brand-700 hover:underline">
        Use la consulta pública
      </RouterLink>
    </p>
  </div>
</template>
