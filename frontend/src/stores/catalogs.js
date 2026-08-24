import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useCatalogStore = defineStore('catalogs', () => {
  const eps = ref([])
  const arls = ref([])
  const cities = ref([])
  const risks = ref([])
  const examTypes = ref([])
  const loaded = ref(false)
  const loading = ref(false)

  async function load(force = false) {
    if (loaded.value && !force) return
    if (loading.value) return

    loading.value = true

    try {
      const { data } = await api.get('/catalogs')

      eps.value = data.eps
      arls.value = data.arls
      cities.value = data.cities
      risks.value = data.risks
      examTypes.value = data.exam_types
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  function findArl(id) {
    return arls.value.find((item) => item.id === Number(id)) || null
  }

  function reset() {
    eps.value = []
    arls.value = []
    cities.value = []
    risks.value = []
    examTypes.value = []
    loaded.value = false
  }

  return { eps, arls, cities, risks, examTypes, loaded, loading, load, findArl, reset }
})
