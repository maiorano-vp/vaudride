import { describe, expect, it } from 'vitest'
import { availableSceneSources, parseSceneSource, resolveSceneSource, SceneSource } from './source'

describe('scene source', () => {
  it('parses known values only', () => {
    expect(parseSceneSource('google')).toBe(SceneSource.Google)
    expect(parseSceneSource('mapbox')).toBeUndefined()
    expect(parseSceneSource(undefined)).toBeUndefined()
  })

  it('offers Google only with a key', () => {
    expect(availableSceneSources(false)).toEqual([SceneSource.Swisstopo, SceneSource.Light])
    expect(availableSceneSources(true)).toContain(SceneSource.Google)
  })

  it('falls back to swisstopo when the stored choice is unknown or unavailable', () => {
    expect(resolveSceneSource('light', false)).toBe(SceneSource.Light)
    expect(resolveSceneSource('google', true)).toBe(SceneSource.Google)
    expect(resolveSceneSource('google', false)).toBe(SceneSource.Swisstopo)
    expect(resolveSceneSource('nope', true)).toBe(SceneSource.Swisstopo)
  })
})
