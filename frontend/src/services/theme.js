/**
 * Aplica el tema configurado escribiendo las mismas variables CSS que genera
 * Tailwind. Al ponerlas inline en <html> ganan sobre las de :root del bundle,
 * asi que el tema cambia sin recompilar.
 */

const FONT_LINK_ID = 'branding-fonts'
const FALLBACK = 'ui-sans-serif, system-ui, sans-serif'

/** Mismos escalones de luminosidad que App\Support\Branding en el backend. */
const SHADES = {
  50: 0.97, 100: 0.94, 200: 0.86, 300: 0.77, 400: 0.66,
  500: 0.55, 600: 0.47, 700: 0.39, 800: 0.32, 900: 0.26,
}

export function applyBranding(branding) {
  if (!branding?.theme) return

  const root = document.documentElement
  const { theme } = branding

  applyPalette(root, 'brand', theme.palette)
  applyPalette(root, 'accent', theme.accent_palette)
  applyFonts(root, theme.font_heading, theme.font_body)
  applyRadius(root, theme.radius)
}

/**
 * Deriva la paleta en el navegador para la vista previa del panel, sin tener
 * que guardar primero. Replica el algoritmo del backend.
 */
export function buildPalette(hex) {
  const [h, s] = hexToHsl(hex)
  const palette = {}

  for (const [shade, lightness] of Object.entries(SHADES)) {
    // Los tonos muy claros se desaturan un poco para que no queden fosforescentes.
    palette[shade] = hslToHex(h, lightness > 0.85 ? s * 0.85 : s, lightness)
  }

  return palette
}

function applyPalette(root, name, palette) {
  if (!palette) return

  for (const [shade, hex] of Object.entries(palette)) {
    root.style.setProperty(`--color-${name}-${shade}`, hex)
  }
}

function applyRadius(root, radius) {
  if (!radius) return

  root.style.setProperty('--radius-lg', radius)
  root.style.setProperty('--radius-xl', `calc(${radius} * 1.33)`)
}

function applyFonts(root, heading, body) {
  const families = [...new Set([heading, body].filter(Boolean))]

  if (!families.length) return

  loadGoogleFonts(families)

  if (heading) root.style.setProperty('--font-display', `'${heading}', ${FALLBACK}`)
  if (body) root.style.setProperty('--font-sans', `'${body}', ${FALLBACK}`)
}

/** Reutiliza un unico <link>: cambiar de fuente reemplaza el href, no acumula. */
function loadGoogleFonts(families) {
  const query = families
    .map((family) => `family=${encodeURIComponent(family).replace(/%20/g, '+')}:wght@400;500;600;700`)
    .join('&')

  const href = `https://fonts.googleapis.com/css2?${query}&display=swap`

  let link = document.getElementById(FONT_LINK_ID)

  if (!link) {
    link = document.createElement('link')
    link.id = FONT_LINK_ID
    link.rel = 'stylesheet'
    document.head.appendChild(link)
  }

  if (link.href !== href) link.href = href
}

function hexToHsl(hex) {
  let value = hex.replace('#', '')

  if (value.length === 3) {
    value = value[0] + value[0] + value[1] + value[1] + value[2] + value[2]
  }

  const r = parseInt(value.slice(0, 2), 16) / 255
  const g = parseInt(value.slice(2, 4), 16) / 255
  const b = parseInt(value.slice(4, 6), 16) / 255

  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  const l = (max + min) / 2
  const d = max - min

  if (d === 0) return [0, 0, l]

  const s = l > 0.5 ? d / (2 - max - min) : d / (max + min)

  let h
  if (max === r) h = (g - b) / d + (g < b ? 6 : 0)
  else if (max === g) h = (b - r) / d + 2
  else h = (r - g) / d + 4

  return [h / 6, s, l]
}

function hslToHex(h, s, l) {
  let r
  let g
  let b

  if (s === 0) {
    r = g = b = l
  } else {
    const q = l < 0.5 ? l * (1 + s) : l + s - l * s
    const p = 2 * l - q

    r = hueToRgb(p, q, h + 1 / 3)
    g = hueToRgb(p, q, h)
    b = hueToRgb(p, q, h - 1 / 3)
  }

  const channel = (v) => Math.round(v * 255).toString(16).padStart(2, '0')

  return `#${channel(r)}${channel(g)}${channel(b)}`
}

function hueToRgb(p, q, t) {
  let value = t

  if (value < 0) value += 1
  if (value > 1) value -= 1

  if (value < 1 / 6) return p + (q - p) * 6 * value
  if (value < 1 / 2) return q
  if (value < 2 / 3) return p + (q - p) * (2 / 3 - value) * 6

  return p
}
