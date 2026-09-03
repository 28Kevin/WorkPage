/**
 * Los mismos empleadores se repiten examen tras examen, así que se guardan en
 * el navegador para autocompletar la razón social junto con su NIT. No viajan
 * al servidor: son una comodidad local del equipo donde se digita.
 */

const STORAGE_KEY = 'emo.employers'
const MAX_ENTRIES = 30

/** Compara razones sociales sin distinguir mayúsculas ni espacios sobrantes. */
function sameName(a, b) {
  return (a || '').trim().toLowerCase() === (b || '').trim().toLowerCase()
}

function persist(list) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list))
  } catch {
    // Modo privado o almacenamiento lleno: se pierde la comodidad, no el examen.
  }
}

/** Lee la lista guardada; ante cualquier dato corrupto devuelve vacío. */
export function loadEmployers() {
  try {
    const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')

    if (!Array.isArray(raw)) return []

    return raw
      .filter((item) => item && typeof item.name === 'string' && item.name.trim())
      .map((item) => ({ name: item.name.trim(), nit: String(item.nit || '').trim() }))
      .slice(0, MAX_ENTRIES)
  } catch {
    return []
  }
}

/** Guarda o actualiza un empleador y lo deja de primero en la lista. */
export function rememberEmployer(name, nit) {
  const clean = (name || '').trim()

  if (!clean) return loadEmployers()

  const rest = loadEmployers().filter((item) => !sameName(item.name, clean))
  const next = [{ name: clean, nit: String(nit || '').trim() }, ...rest].slice(0, MAX_ENTRIES)

  persist(next)

  return next
}

/** Quita un empleador de la lista. */
export function forgetEmployer(name) {
  const next = loadEmployers().filter((item) => !sameName(item.name, name))

  persist(next)

  return next
}

/** Busca por razón social exacta. Devuelve null si no está guardada. */
export function findEmployer(name) {
  return loadEmployers().find((item) => sameName(item.name, name)) || null
}
