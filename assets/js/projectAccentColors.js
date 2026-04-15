/** Utilitaires couleurs pastille projet (#RRGGBB). */

export function normalizeHex(hex) {
  if (typeof hex !== 'string') return null;
  const h = hex.trim();
  return /^#[0-9A-Fa-f]{6}$/.test(h) ? h.toLowerCase() : null;
}

function clamp01(n) {
  if (!Number.isFinite(n)) return 0;
  return Math.min(1, Math.max(0, n));
}

function parseHexColor(hex) {
  const h = normalizeHex(hex);
  if (!h) return null;
  return {
    r: Number.parseInt(h.slice(1, 3), 16),
    g: Number.parseInt(h.slice(3, 5), 16),
    b: Number.parseInt(h.slice(5, 7), 16),
  };
}

function srgbToLinear(u8) {
  const x = u8 / 255;
  return x <= 0.04045 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4;
}

function relativeLuminance({ r, g, b }) {
  const R = srgbToLinear(r);
  const G = srgbToLinear(g);
  const B = srgbToLinear(b);
  return 0.2126 * R + 0.7152 * G + 0.0722 * B;
}

/** Texte lisible sur un fond hex. */
export function contrastTextForBackground(hex) {
  const rgb = parseHexColor(hex);
  if (!rgb) return '#0f172a';
  const L = relativeLuminance(rgb);
  return L > 0.5 ? '#0f172a' : '#ffffff';
}

function mix(a, b, t) {
  const tt = clamp01(t);
  return Math.round(a + (b - a) * tt);
}

/** Bordure un peu plus foncée que le fond (hex #RRGGBB). */
export function darkenBorderHex(hex, amount = 0.25) {
  const rgb = parseHexColor(hex);
  if (!rgb) return '#475569';
  const r = mix(rgb.r, 0, amount);
  const g = mix(rgb.g, 0, amount);
  const b = mix(rgb.b, 0, amount);
  return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
}

/** Même palette que `Project::randomAccentColor()` côté PHP. */
export const PROJECT_ACCENT_PALETTE = [
  '#ff5a36',
  '#3b82f6',
  '#10b981',
  '#8b5cf6',
  '#f59e0b',
  '#ec4899',
  '#06b6d4',
  '#84cc16',
  '#ef4444',
  '#6366f1',
];

export function randomAccentBackground() {
  return PROJECT_ACCENT_PALETTE[Math.floor(Math.random() * PROJECT_ACCENT_PALETTE.length)];
}
