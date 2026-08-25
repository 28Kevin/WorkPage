import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useGalleryStore = defineStore('gallery', () => {
  const images = ref([])
  const loading = ref(false)
  const loaded = ref(false)

  /** Galería pública: solo las imágenes activas, ya ordenadas. */
  async function load(force = false) {
    if (loaded.value && !force) return images.value
    if (loading.value) return images.value

    loading.value = true

    try {
      const { data } = await api.get('/gallery')

      images.value = data.data
      loaded.value = true
    } catch {
      // Sin galería las páginas públicas siguen funcionando.
      images.value = []
    } finally {
      loading.value = false
    }

    return images.value
  }

  /** Panel: incluye las ocultas para poder reactivarlas. */
  async function loadAll() {
    loading.value = true

    try {
      const { data } = await api.get('/gallery/all')
      images.value = data.data
      loaded.value = true
    } finally {
      loading.value = false
    }

    return images.value
  }

  async function create(payload) {
    const { data } = await api.post('/gallery', payload)

    images.value = [...images.value, data.data]

    return data.data
  }

  async function update(id, payload) {
    const { data } = await api.patch(`/gallery/${id}`, payload)

    images.value = images.value.map((image) => (image.id === id ? data.data : image))

    return data.data
  }

  async function remove(id) {
    await api.delete(`/gallery/${id}`)

    images.value = images.value.filter((image) => image.id !== id)
  }

  return { images, loading, loaded, load, loadAll, create, update, remove }
})
