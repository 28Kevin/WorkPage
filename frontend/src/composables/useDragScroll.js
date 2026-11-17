import { ref } from 'vue'

/**
 * Desplazamiento horizontal con la rueda del mouse y arrastrando. Con el dedo
 * ya funciona el scroll nativo. Al llegar a un extremo la rueda vuelve a mover
 * la página, para no dejar al usuario atrapado.
 *
 * Uso: const { track, dragging, handlers } = useDragScroll()
 *      <div ref="track" v-on="handlers">
 */
export function useDragScroll() {
  const track = ref(null)
  const dragging = ref(false)

  function onWheel(event) {
    const element = track.value
    const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY
    const max = element.scrollWidth - element.clientWidth

    if (max <= 0) return
    if (delta < 0 && element.scrollLeft <= 0) return
    if (delta > 0 && element.scrollLeft >= max - 1) return

    event.preventDefault()
    element.scrollLeft += delta
  }

  let drag = null

  function onPointerdown(event) {
    if (event.pointerType !== 'mouse' || event.button !== 0) return

    drag = { x: event.clientX, left: track.value.scrollLeft, moved: false }
  }

  function onPointermove(event) {
    if (!drag) return

    const distance = event.clientX - drag.x

    // Un clic con un temblor mínimo no cuenta como arrastre.
    if (!drag.moved && Math.abs(distance) < 5) return

    if (!drag.moved) {
      drag.moved = true
      dragging.value = true
      track.value.setPointerCapture(event.pointerId)
    }

    track.value.scrollLeft = drag.left - distance
  }

  function onPointerup() {
    drag = null
    dragging.value = false
  }

  return {
    track,
    dragging,
    handlers: {
      wheel: onWheel,
      pointerdown: onPointerdown,
      pointermove: onPointermove,
      pointerup: onPointerup,
      pointercancel: onPointerup,
      dragstart: (event) => event.preventDefault(),
    },
  }
}
