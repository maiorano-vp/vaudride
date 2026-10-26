/** A player's equipment: colours of the five recolourable parts and three style variants. */
export const JERSEY_STYLES = ['solid', 'stripes', 'band'] as const
export const HELMET_STYLES = ['road', 'aero', 'cap'] as const
export const WHEEL_STYLES = ['spoked', 'disc'] as const

export type JerseyStyle = (typeof JERSEY_STYLES)[number]
export type HelmetStyle = (typeof HELMET_STYLES)[number]
export type WheelStyle = (typeof WHEEL_STYLES)[number]

export const KIT_COLORS = ['jersey', 'accent', 'shorts', 'helmet', 'bike'] as const
export type KitColor = (typeof KIT_COLORS)[number]

export interface Kit extends Record<KitColor, string> {
  jerseyStyle: JerseyStyle
  helmetStyle: HelmetStyle
  wheels: WheelStyle
}

export const DEFAULT_KIT: Kit = {
  jersey: '#0a7d3b',
  accent: '#ffffff',
  shorts: '#222222',
  helmet: '#ffffff',
  bike: '#0a4069',
  jerseyStyle: 'solid',
  helmetStyle: 'road',
  wheels: 'spoked',
}

export const KIT_PRESETS: Record<string, Kit> = {
  vaud: DEFAULT_KIT,
  lavaux: { ...DEFAULT_KIT, jersey: '#f4c430', accent: '#7a1f3d', shorts: '#7a1f3d', helmet: '#7a1f3d', bike: '#f4c430', jerseyStyle: 'band' },
  lac: { ...DEFAULT_KIT, jersey: '#2f729f', accent: '#ffffff', shorts: '#0a334d', helmet: '#ffffff', bike: '#ffffff', jerseyStyle: 'stripes', helmetStyle: 'aero', wheels: 'disc' },
  rouge: { ...DEFAULT_KIT, jersey: '#d62828', accent: '#111111', shorts: '#111111', helmet: '#111111', bike: '#111111', helmetStyle: 'cap' },
}

export const isHexColor = (v: unknown): v is string => typeof v === 'string' && /^#[0-9a-fA-F]{6}$/.test(v)

const pick = <T extends string>(options: readonly T[], value: unknown, fallback: T): T =>
  options.find((o) => o === value) ?? fallback

/** Anything that came over the network or from storage → a complete, valid kit (field by field fallback). */
export function sanitizeKit(input: unknown): Kit {
  const k = (input && typeof input === 'object' ? input : {}) as Record<string, unknown>
  const color = (name: KitColor) => (isHexColor(k[name]) ? (k[name] as string).toLowerCase() : DEFAULT_KIT[name])
  return {
    jersey: color('jersey'),
    accent: color('accent'),
    shorts: color('shorts'),
    helmet: color('helmet'),
    bike: color('bike'),
    jerseyStyle: pick(JERSEY_STYLES, k.jerseyStyle, DEFAULT_KIT.jerseyStyle),
    helmetStyle: pick(HELMET_STYLES, k.helmetStyle, DEFAULT_KIT.helmetStyle),
    wheels: pick(WHEEL_STYLES, k.wheels, DEFAULT_KIT.wheels),
  }
}

const toLinear = (hex: string): number[] =>
  [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).concat(1)

const GLB_MAGIC = 0x46546c67
const JSON_CHUNK = 0x4e4f534a

/** Returns a copy of a GLB with the kit's colours baked into its materials (matched by name). */
export function applyKit(glb: Uint8Array, kit: Kit): Uint8Array {
  const view = new DataView(glb.buffer, glb.byteOffset, glb.byteLength)
  if (view.getUint32(0, true) !== GLB_MAGIC || view.getUint32(16, true) !== JSON_CHUNK) throw new Error('Not a GLB file')
  const jsonLength = view.getUint32(12, true)
  const doc = JSON.parse(new TextDecoder().decode(glb.subarray(20, 20 + jsonLength)))
  for (const material of doc.materials ?? []) {
    if ((KIT_COLORS as readonly string[]).includes(material.name)) {
      material.pbrMetallicRoughness.baseColorFactor = toLinear(kit[material.name as KitColor])
    }
  }
  const json = new TextEncoder().encode(JSON.stringify(doc))
  const padded = new Uint8Array(json.length + ((4 - (json.length % 4)) % 4)).fill(0x20)
  padded.set(json)
  const rest = glb.subarray(20 + jsonLength) // the BIN chunk, untouched
  const out = new Uint8Array(20 + padded.length + rest.length)
  const outView = new DataView(out.buffer)
  outView.setUint32(0, GLB_MAGIC, true)
  outView.setUint32(4, 2, true)
  outView.setUint32(8, out.length, true)
  outView.setUint32(12, padded.length, true)
  outView.setUint32(16, JSON_CHUNK, true)
  out.set(padded, 20)
  out.set(rest, 20 + padded.length)
  return out
}

/** Which style-variant nodes are visible for a kit. */
export const visibleNodes = (kit: Kit): Record<string, boolean> => ({
  jersey_stripes: kit.jerseyStyle === 'stripes',
  jersey_band: kit.jerseyStyle === 'band',
  helmet_road: kit.helmetStyle === 'road',
  helmet_aero: kit.helmetStyle === 'aero',
  helmet_cap: kit.helmetStyle === 'cap',
  wheel_front_spoked: kit.wheels === 'spoked',
  wheel_front_disc: kit.wheels === 'disc',
  wheel_rear_spoked: kit.wheels === 'spoked',
  wheel_rear_disc: kit.wheels === 'disc',
})
