import { describe, expect, it } from 'vitest'
import { availableSceneSources, isPhotorealistic, parseSceneSource, resolveSceneSource, SceneSource } from './source'

const none = { google: false, ion: false }

describe('scene source', () => {
  it('parses known values only', () => {
    expect(parseSceneSource('googleIon')).toBe(SceneSource.GoogleIon)
    expect(parseSceneSource('mapbox')).toBeUndefined()
    expect(parseSceneSource(undefined)).toBeUndefined()
  })

  it('offers each Google source only with its own credential', () => {
    expect(availableSceneSources(none)).toEqual([SceneSource.Swisstopo, SceneSource.Light])
    expect(availableSceneSources({ google: true, ion: false })).toContain(SceneSource.Google)
    expect(availableSceneSources({ google: true, ion: false })).not.toContain(SceneSource.GoogleIon)
    expect(availableSceneSources({ google: false, ion: true })).toContain(SceneSource.GoogleIon)
  })

  it('offers the mixed source when any Google credential exists', () => {
    expect(availableSceneSources(none)).not.toContain(SceneSource.Mixed)
    expect(availableSceneSources({ google: true, ion: false })).toContain(SceneSource.Mixed)
    expect(availableSceneSources({ google: false, ion: true })).toContain(SceneSource.Mixed)
  })

  it('falls back to swisstopo when the stored choice is unknown or unavailable', () => {
    expect(resolveSceneSource('light', none)).toBe(SceneSource.Light)
    expect(resolveSceneSource('googleIon', { google: false, ion: true })).toBe(SceneSource.GoogleIon)
    expect(resolveSceneSource('googleIon', none)).toBe(SceneSource.Swisstopo)
    expect(resolveSceneSource('nope', { google: true, ion: true })).toBe(SceneSource.Swisstopo)
  })

  it('flags the sources that replace the globe', () => {
    expect([SceneSource.Google, SceneSource.GoogleIon].every(isPhotorealistic)).toBe(true)
    expect([SceneSource.Swisstopo, SceneSource.Light, SceneSource.Mixed].some(isPhotorealistic)).toBe(false)
  })
})
