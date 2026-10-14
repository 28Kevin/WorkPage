import { computed, ref } from 'vue'

/** Tamaños de página que ofrece el panel. Deben coincidir con el backend. */
export const PAGE_SIZES = [10, 15, 25, 50, 100]

/**
 * Estado de orden y tamaño de página de un listado.
 *
 * Vive aparte porque los cuatro listados del panel lo comparten; cada vista
 * sigue encargándose de sus propios filtros y de pedir los datos.
 */
export function useTableQuery(defaultSort, defaultDirection = 'desc') {
  const sort = ref(defaultSort)
  const direction = ref(defaultDirection)
  const perPage = ref(15)

  /**
   * Al pulsar una columna distinta se ordena por ella en la dirección natural
   * del campo (los textos de la A a la Z, las fechas y los números de mayor a
   * menor); al repetir sobre la misma, se invierte.
   */
  function toggleSort(field, first = 'desc') {
    if (sort.value === field) {
      direction.value = direction.value === 'asc' ? 'desc' : 'asc'

      return
    }

    sort.value = field
    direction.value = first
  }

  const params = computed(() => ({
    sort: sort.value,
    direction: direction.value,
    per_page: perPage.value,
  }))

  return { sort, direction, perPage, toggleSort, params }
}
