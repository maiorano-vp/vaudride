import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { applyKit, DEFAULT_KIT, isHexColor, KIT_PRESETS, sanitizeKit, visibleNodes } from './kit'

const glb = new Uint8Array(readFileSync(new URL('../../../public/models/cyclist.glb', import.meta.url)))

function readJson(bytes: Uint8Array) {
  const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength)
  return JSON.parse(new TextDecoder().decode(bytes.subarray(20, 20 + view.getUint32(12, true))))
}

describe('kit', () => {
  it('accepts only #rrggbb colours', () => {
    expect(isHexColor('#0a7d3b')).toBe(true)
    expect(['red', '#fff', '#12345g', 12, undefined].some(isHexColor)).toBe(false)
  })

  it('repairs a kit field by field', () => {
    const kit = sanitizeKit({ jersey: '#FF0000', accent: 'nope', jerseyStyle: 'stripes', helmetStyle: 'hat', wheels: 'disc' })
    expect(kit).toEqual({ ...DEFAULT_KIT, jersey: '#ff0000', jerseyStyle: 'stripes', wheels: 'disc' })
    expect(sanitizeKit(null)).toEqual(DEFAULT_KIT)
    expect(sanitizeKit('x')).toEqual(DEFAULT_KIT)
  })

  it('ships presets that are valid kits', () => {
    for (const preset of Object.values(KIT_PRESETS)) expect(sanitizeKit(preset)).toEqual(preset)
  })

  it('bakes the colours into a valid GLB without touching the geometry', () => {
    const out = applyKit(glb, { ...DEFAULT_KIT, jersey: '#ff0000' })
    const view = new DataView(out.buffer)
    expect(view.getUint32(8, true)).toBe(out.length)
    expect(out.length % 4).toBe(0)
    const jersey = readJson(out).materials.find((m: { name: string }) => m.name === 'jersey')
    expect(jersey.pbrMetallicRoughness.baseColorFactor.slice(0, 3)).toEqual([1, 0, 0])
    // the binary chunk is carried over byte for byte
    expect(Array.from(out.subarray(out.length - 64))).toEqual(Array.from(glb.subarray(glb.length - 64)))
    // skin and shoes keep their own colour
    const skin = readJson(out).materials.find((m: { name: string }) => m.name === 'skin')
    expect(skin.pbrMetallicRoughness.baseColorFactor).toEqual(readJson(glb).materials.find((m: { name: string }) => m.name === 'skin').pbrMetallicRoughness.baseColorFactor)
  })

  it('refuses something that is not a GLB', () => {
    expect(() => applyKit(new Uint8Array(40), DEFAULT_KIT)).toThrow()
  })

  it('exposes every style node the model has, and shows exactly one of each family', () => {
    const names = new Set<string>(readJson(glb).nodes.map((n: { name: string }) => n.name))
    const shown = visibleNodes(DEFAULT_KIT)
    for (const node of Object.keys(shown)) expect(names.has(node)).toBe(true)
    expect(Object.entries(shown).filter(([n, on]) => on && n.startsWith('helmet_'))).toHaveLength(1)
  })
})
