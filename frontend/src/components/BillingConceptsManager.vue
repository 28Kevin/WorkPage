<script setup>
import { onMounted, ref } from 'vue'
import api, { parseApiError } from '@/services/api'

/**
 * Opciones de la lista desplegable de ítems de la cuenta de cobro. Al retirar
 * una no se borra: las cuentas ya emitidas guardan su propio texto, y dejarla
 * inactiva permite volver a activarla.
 */
const concepts = ref([])
const loading = ref(true)
const error = ref(null)
const newName = ref('')
const busy = ref(null)

onMounted(load)

async function load() {
  loading.value = true

  try {
    const { data } = await api.get('/billing/concepts')
    concepts.value = data.data
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    loading.value = false
  }
}

async function add() {
  const name = newName.value.trim()

  if (!name) return

  busy.value = 'new'
  error.value = null

  try {
    await api.post('/billing/concepts', { name })
    newName.value = ''
    await load()
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    busy.value = null
  }
}

async function rename(concept) {
  const name = concept.name.trim()

  if (!name) return

  busy.value = concept.id
  error.value = null

  try {
    await api.patch(`/billing/concepts/${concept.id}`, { name })
  } catch (err) {
    error.value = parseApiError(err).message
    await load()
  } finally {
    busy.value = null
  }
}

async function toggle(concept) {
  busy.value = concept.id
  error.value = null

  try {
    await api.patch(`/billing/concepts/${concept.id}`, { active: !concept.active })
    await load()
  } catch (err) {
    error.value = parseApiError(err).message
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="p-5">
    <p class="mb-4 text-sm text-slate-600">
      Opciones de la lista desplegable de ítems. Al retirar una deja de aparecer en el formulario,
      pero las cuentas ya emitidas conservan su texto.
    </p>

    <p v-if="error" class="field-error mb-3">{{ error }}</p>

    <p v-if="loading" class="text-sm text-slate-500">Cargando conceptos…</p>

    <ul v-else class="space-y-2">
      <li
        v-for="concept in concepts"
        :key="concept.id"
        class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 p-2"
        :class="{ 'opacity-60': !concept.active }"
      >
        <input
          v-model="concept.name"
          type="text"
          class="field-input flex-1"
          :disabled="busy === concept.id"
          :aria-label="`Concepto ${concept.id}`"
          @blur="rename(concept)"
        >

        <button
          type="button"
          class="btn-ghost text-xs"
          :class="concept.active ? 'text-red-700' : 'text-emerald-700'"
          :disabled="busy === concept.id"
          @click="toggle(concept)"
        >
          {{ concept.active ? 'Retirar' : 'Reactivar' }}
        </button>
      </li>
    </ul>

    <div class="mt-4 flex flex-wrap items-end gap-2">
      <div class="flex-1">
        <label for="new-concept" class="field-label">Nuevo concepto</label>
        <input
          id="new-concept"
          v-model="newName"
          type="text"
          class="field-input"
          placeholder="Certificado de…"
          @keydown.enter.prevent="add"
        >
      </div>

      <button type="button" class="btn-secondary" :disabled="busy === 'new' || !newName.trim()" @click="add">
        Agregar
      </button>
    </div>
  </div>
</template>
