import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useCatalogStore = defineStore('catalogs', () => {
  const arls = ref([])
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

      arls.value = data.arls
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

  function reset() {
    arls.value = []
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
    arls, risks,
    examTypes, documentTypes, sexes, aptitudeResults,
    systems, paraclinicals, assessments, aptitudes,
    loaded, loading, load, findArl, reset,
  }
})
