<script setup>
import { onMounted, reactive, ref } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import { parseApiError } from '@/services/api'
import { prepareImage } from '@/services/images'
import { useGalleryStore } from '@/stores/gallery'

const gallery = useGalleryStore()

/** Ancho máximo guardado: suficiente para las tarjetas de la galería. */
const MAX_IMAGE_PX = 1200

const form = reactive({ title: '', caption: '', image: null })

const errors = ref({})
const alert = ref(null)
const saving = ref(false)
const busy = ref(null)
const deleting = ref(null)

onMounted(() => gallery.loadAll().catch(() => null))

async function pickImage(event) {
  const file = event.target.files?.[0]

  errors.value = {}

  if (!file) return

  try {
    form.image = await prepareImage(file, MAX_IMAGE_PX)
  } catch (error) {
    alert.value = {
      variant: 'error',
      text: error.message === 'too-large'
        ? 'La imagen supera los 5 MB. Use una más liviana.'
        : 'No fue posible leer la imagen.',
    }
  } finally {
    event.target.value = ''
  }
}

async function submit() {
  saving.value = true
  errors.value = {}
  alert.value = null

  try {
    await gallery.create({ ...form })

    Object.assign(form, { title: '', caption: '', image: null })
    alert.value = { variant: 'success', text: 'Imagen añadida a la galería.' }
  } catch (error) {
    const parsed = parseApiError(error)

    errors.value = parsed.errors
    alert.value = { variant: 'error', text: parsed.message }
  } finally {
    saving.value = false
  }
}

async function toggle(image) {
  busy.value = image.id

  try {
    await gallery.update(image.id, { title: image.title, active: !image.active })
  } catch (error) {
    alert.value = { variant: 'error', text: parseApiError(error).message }
  } finally {
    busy.value = null
  }
}

async function move(image, delta) {
  busy.value = image.id

  try {
    await gallery.update(image.id, {
      title: image.title,
      position: Math.max(0, image.position + delta),
    })
    await gallery.loadAll()
  } catch (error) {
    alert.value = { variant: 'error', text: parseApiError(error).message }
  } finally {
    busy.value = null
  }
}

async function remove(image) {
  busy.value = image.id

  try {
    await gallery.remove(image.id)
    deleting.value = null
  } catch (error) {
    alert.value = { variant: 'error', text: parseApiError(error).message }
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="space-y-5 p-5">
    <p class="text-xs text-slate-500">
      Estas fotografías se muestran en las páginas de Servicios y Contáctenos. Se guardan dentro de la base
      de datos, así que sobreviven a los despliegues.
    </p>

    <AlertMessage v-if="alert" :variant="alert.variant">{{ alert.text }}</AlertMessage>

    <!-- ------------------------------------------------------------- alta -->
    <div class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-3">
      <FormField label="Título" :error="errors.title" required>
        <template #default="{ id }">
          <input :id="id" v-model="form.title" type="text" class="field-input" maxlength="120"
                 placeholder="Ej. Sala de valoración">
        </template>
      </FormField>

      <FormField label="Descripción" :error="errors.caption" hint="Opcional.">
        <template #default="{ id }">
          <input :id="id" v-model="form.caption" type="text" class="field-input" maxlength="200">
        </template>
      </FormField>

      <div>
        <span class="field-label">Imagen</span>

        <div class="flex items-center gap-3">
          <div class="flex h-14 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white">
            <img v-if="form.image" :src="form.image" alt="" class="h-full w-full object-cover">
            <span v-else class="text-xs text-slate-400">Sin foto</span>
          </div>

          <label class="btn-secondary cursor-pointer py-1.5 text-xs">
            <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickImage">
            Seleccionar
          </label>
        </div>

        <p v-if="errors.image" class="field-error">{{ errors.image[0] }}</p>
      </div>

      <div class="md:col-span-3 flex justify-end">
        <button
          type="button"
          class="btn-primary"
          :disabled="saving || !form.title || !form.image"
          @click="submit"
        >
          {{ saving ? 'Subiendo…' : 'Añadir a la galería' }}
        </button>
      </div>
    </div>

    <!-- ---------------------------------------------------------- listado -->
    <div v-if="!gallery.images.length" class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center">
      <p class="text-sm font-medium text-slate-900">La galería está vacía</p>
      <p class="mt-1 text-xs text-slate-500">
        Suba fotografías del centro médico y de los exámenes para que aparezcan en las páginas públicas.
      </p>
    </div>

    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <figure
        v-for="image in gallery.images"
        :key="image.id"
        class="overflow-hidden rounded-lg border border-slate-200"
        :class="image.active ? '' : 'opacity-60'"
      >
        <img :src="image.url" :alt="image.title" loading="lazy" class="h-32 w-full object-cover">

        <figcaption class="space-y-2 p-3">
          <div>
            <p class="text-sm font-semibold text-slate-900">{{ image.title }}</p>
            <p v-if="image.caption" class="text-xs text-slate-500">{{ image.caption }}</p>
          </div>

          <div class="flex flex-wrap items-center gap-1">
            <button type="button" class="btn-ghost px-2 py-1 text-xs" :disabled="busy === image.id"
                    title="Mover antes" @click="move(image, -1)">
              ↑
            </button>
            <button type="button" class="btn-ghost px-2 py-1 text-xs" :disabled="busy === image.id"
                    title="Mover después" @click="move(image, 1)">
              ↓
            </button>

            <button type="button" class="btn-ghost px-2 py-1 text-xs" :disabled="busy === image.id"
                    @click="toggle(image)">
              {{ image.active ? 'Ocultar' : 'Mostrar' }}
            </button>

            <button type="button" class="ml-auto rounded px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50"
                    @click="deleting = image">
              Eliminar
            </button>
          </div>
        </figcaption>
      </figure>
    </div>

    <!-- ------------------------------------------------------- confirmación -->
    <div
      v-if="deleting"
      class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="gallery-delete-title"
    >
      <div class="card w-full max-w-md p-5">
        <h2 id="gallery-delete-title" class="text-sm font-bold text-slate-900">Eliminar la imagen</h2>
        <p class="mt-2 text-sm text-slate-600">
          Se borra «{{ deleting.title }}» de la galería. Esta acción no se puede deshacer.
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
