import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useCatalogStore = defineStore('catalogs', () => {
  const eps = ref([])
  const arls = ref([])
  const afps = ref([])
  const cities = ref([])
  const risks = ref([])
  const examTypes = ref([])
  const documentTypes = ref([])
  const sexes = ref([])
  const aptitudeResults = ref([])

  // Definiciones de los bloques semiestructurados del formato ocupacional.
  const systems = ref([])
  const paraclinicals = ref([])
  const assessments = ref([])
  const aptitudes = ref([])

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
      afps.value = data.afps ?? []
      cities.value = data.cities
      risks.value = data.risks
      examTypes.value = data.exam_types
      documentTypes.value = data.document_types ?? []
      sexes.value = data.sexes ?? []
      aptitudeResults.value = data.aptitude_results ?? []

      systems.value = data.form?.systems ?? []
      paraclinicals.value = data.form?.paraclinicals ?? []
      assessments.value = data.form?.assessments ?? []
      aptitudes.value = data.form?.aptitudes ?? []

      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  function findArl(id) {
    return arls.value.find((item) => item.id === Number(id)) || null
  }

  function findCity(id) {
    return cities.value.find((item) => item.id === Number(id)) || null
  }

  function reset() {
    eps.value = []
    arls.value = []
    afps.value = []
    cities.value = []
    risks.value = []
    examTypes.value = []
    documentTypes.value = []
    sexes.value = []
    aptitudeResults.value = []
    systems.value = []
    paraclinicals.value = []
    assessments.value = []
    aptitudes.value = []
    loaded.value = false
  }

  return {
    eps, arls, afps, cities, risks,
    examTypes, documentTypes, sexes, aptitudeResults,
    systems, paraclinicals, assessments, aptitudes,
    loaded, loading, load, findArl, findCity, reset,
  }
})
