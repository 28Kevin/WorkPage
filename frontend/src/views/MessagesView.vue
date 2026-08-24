<script setup>
import { onMounted, ref, watch } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'

const messages = ref([])
const meta = ref(null)
const status = ref('pending')
const page = ref(1)
const loading = ref(true)
const error = ref(null)
const busy = ref(null)
const deleting = ref(null)

const TABS = [
  { value: 'pending', label: 'Pendientes' },
  { value: 'handled', label: 'Atendidos' },
  { value: 'all', label: 'Todos' },
]

async function fetchMessages() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get('/contact-messages', {
      params: { status: status.value, page: page.value },
    })

    messages.value = data.data
    meta.value = data.meta
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
}

onMounted(fetchMessages)

watch(status, () => {
  page.value = 1
  fetchMessages()
})

watch(page, fetchMessages)

async function toggle(message) {
  busy.value = message.id

  try {
    const { data } = await api.patch(`/contact-messages/${message.id}`)

    // Si el filtro activo ya no lo incluye, se recarga la lista.
    if (status.value === 'all') {
      Object.assign(message, data.data)
    } else {
      await fetchMessages()
    }
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    busy.value = null
  }
}

async function remove(message) {
  busy.value = message.id

  try {
    await api.delete(`/contact-messages/${message.id}`)
    deleting.value = null
    await fetchMessages()
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    busy.value = null
  }
}

function formatDate(value) {
  return new Date(value).toLocaleString('es-CO', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Mensajes de contacto</h1>
        <p class="mt-1 text-sm text-slate-600">
          {{ meta?.total ?? 0 }} mensaje(s) recibidos desde el formulario público.
        </p>
      </div>

      <div class="flex gap-1 rounded-lg border border-slate-200 p-1">
        <button
          v-for="tab in TABS"
          :key="tab.value"
          type="button"
          class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
          :class="status === tab.value ? 'bg-brand-50 text-brand-800' : 'text-slate-500 hover:bg-slate-100'"
          @click="status = tab.value"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <AlertMessage v-if="error" variant="error" class="mb-4">{{ error }}</AlertMessage>

    <LoadingSpinner v-if="loading" label="Cargando mensajes…" />

    <div v-else-if="!messages.length" class="card px-6 py-14 text-center">
      <p class="text-sm font-medium text-slate-900">
        {{ status === 'handled' ? 'No hay mensajes atendidos' : 'No hay mensajes pendientes' }}
      </p>
      <p class="mt-1 text-sm text-slate-500">
        Los mensajes enviados desde la página de contacto aparecen aquí.
      </p>
    </div>

    <div v-else class="space-y-3">
      <article
        v-for="message in messages"
        :key="message.id"
        class="card p-5"
        :class="message.handled ? 'opacity-70' : ''"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-sm font-bold text-slate-900">{{ message.subject }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">
              {{ message.name }} ·
              <a :href="`mailto:${message.email}`" class="text-brand-700 hover:underline">{{ message.email }}</a>
              <template v-if="message.phone">
                ·
                <a :href="`tel:${message.phone}`" class="text-brand-700 hover:underline">{{ message.phone }}</a>
              </template>
            </p>
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <span
              v-if="message.handled"
              class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-300 ring-inset"
            >
              Atendido
            </span>
            <span class="text-xs whitespace-nowrap text-slate-400">{{ formatDate(message.created_at) }}</span>
          </div>
        </div>

        <p class="mt-3 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ message.message }}</p>

        <p v-if="message.handled && message.handled_by" class="mt-2 text-xs text-slate-400">
          Atendido por {{ message.handled_by }}
        </p>

        <div class="mt-4 flex flex-wrap justify-end gap-2">
          <a :href="`mailto:${message.email}?subject=Re: ${encodeURIComponent(message.subject)}`" class="btn-secondary py-1.5 text-xs">
            Responder
          </a>

          <button
            type="button"
            class="btn-secondary py-1.5 text-xs"
            :disabled="busy === message.id"
            @click="toggle(message)"
          >
            {{ message.handled ? 'Marcar como pendiente' : 'Marcar como atendido' }}
          </button>

          <button
            type="button"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
            @click="deleting = message"
          >
            Eliminar
          </button>
        </div>
      </article>

      <div v-if="meta && meta.last_page > 1"
           class="card flex items-center justify-between px-4 py-3 text-sm">
        <span class="text-slate-600">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <div class="flex gap-2">
          <button type="button" class="btn-secondary py-1.5 text-xs" :disabled="page <= 1" @click="page--">
            Anterior
          </button>
          <button type="button" class="btn-secondary py-1.5 text-xs" :disabled="page >= meta.last_page" @click="page++">
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <!-- ------------------------------------------------------- eliminar mensaje -->
    <div
      v-if="deleting"
      class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-title"
    >
      <div class="card w-full max-w-md p-5">
        <h2 id="delete-title" class="text-sm font-bold text-slate-900">Eliminar el mensaje</h2>
        <p class="mt-2 text-sm text-slate-600">
          Se borra definitivamente «{{ deleting.subject }}», de {{ deleting.name }}. Esta acción no se puede
          deshacer.
        </p>

        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="deleting = null">Cancelar</button>
          <button
            type="button"
            class="btn-primary bg-red-700 hover:bg-red-800 focus-visible:ring-red-500"
            :disabled="busy === deleting.id"
            @click="remove(deleting)"
          >
            Eliminar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
