import { describe, expect, it } from 'vitest'
import { FpsWatchdog, lighter, nextLower, parseChoice, PROFILES, recommendQuality, resolveQuality } from './quality'

const strong = { webgl2: true, deviceMemory: 8, hardwareConcurrency: 12 }

describe('recommendQuality', () => {
  it('goes for the full scene on a strong machine, and on one that tells nothing', () => {
    expect(recommendQuality(strong)).toBe('high')
    expect(recommendQuality({ webgl2: true })).toBe('high')
  })

  it('lightens the scene for modest memory or few cores', () => {
    expect(recommendQuality({ ...strong, deviceMemory: 4 })).toBe('balanced')
    expect(recommendQuality({ ...strong, hardwareConcurrency: 4 })).toBe('balanced')
    expect(recommendQuality({ ...strong, deviceMemory: 2 })).toBe('low')
    expect(recommendQuality({ ...strong, hardwareConcurrency: 2 })).toBe('low')
  })

  it('saves the battery below 20 % when not charging, not otherwise', () => {
    expect(recommendQuality({ ...strong, battery: { level: 0.15, charging: false } })).toBe('low')
    expect(recommendQuality({ ...strong, battery: { level: 0.15, charging: true } })).toBe('high')
    expect(recommendQuality({ ...strong, battery: { level: 0.5, charging: false } })).toBe('high')
  })

  it('respects the request for less data', () => {
    expect(recommendQuality({ ...strong, saveData: true })).toBe('low')
  })

  it('falls back to 2D without WebGL2, whatever else', () => {
    expect(recommendQuality({ ...strong, webgl2: false })).toBe('2d')
    expect(resolveQuality('high', { webgl2: false })).toBe('2d')
  })
})

describe('resolveQuality', () => {
  it('follows the rider’s choice, and the device in auto', () => {
    expect(resolveQuality('low', strong)).toBe('low')
    expect(resolveQuality('2d', strong)).toBe('2d')
    expect(resolveQuality('auto', { ...strong, deviceMemory: 2 })).toBe('low')
  })
})

describe('levels', () => {
  it('steps down to the lightest and stops', () => {
    expect(nextLower('high')).toBe('balanced')
    expect(nextLower('balanced')).toBe('low')
    expect(nextLower('low')).toBeUndefined()
    expect(lighter('high', 'low')).toBe('low')
    expect(lighter('low', 'balanced')).toBe('low')
  })

  it('gets lighter at every step', () => {
    expect(PROFILES.high.resolutionScale).toBeGreaterThan(PROFILES.balanced.resolutionScale)
    expect(PROFILES.balanced.resolutionScale).toBeGreaterThan(PROFILES.low.resolutionScale)
    expect(PROFILES.high.maxOtherModels).toBeGreaterThan(PROFILES.low.maxOtherModels)
    expect(PROFILES.low.targetFrameRate).toBe(30)
  })

  it('reads a stored choice, auto when it is unknown', () => {
    expect(parseChoice('balanced')).toBe('balanced')
    expect(parseChoice('2d')).toBe('2d')
    expect(parseChoice('ultra')).toBe('auto')
    expect(parseChoice(undefined)).toBe('auto')
  })
})

describe('FpsWatchdog', () => {
  /** Draws frames every `frameMs` from `from` to `to` (ms); the watchdog's answers. */
  const run = (dog: FpsWatchdog, from: number, to: number, frameMs: number) => {
    const answers: boolean[] = []
    for (let t = from; t < to; t += frameMs) answers.push(dog.frame(t))
    return answers
  }

  it('stays quiet at a good frame rate', () => {
    expect(run(new FpsWatchdog(), 0, 60_000, 16).some(Boolean)).toBe(false)
  })

  it('fires after two slow windows, once the warm-up is over', () => {
    const answers = run(new FpsWatchdog(), 0, 60_000, 80) // 12 fps
    const first = answers.findIndex(Boolean)
    expect(first).toBeGreaterThan(-1)
    expect(first * 80).toBeGreaterThanOrEqual(10_000 + 2 * 5000) // warm-up, then two windows
  })

  it('does not fire during the warm-up', () => {
    expect(run(new FpsWatchdog(), 0, 9_000, 100).some(Boolean)).toBe(false)
  })

  it('does not count one slow window among good ones', () => {
    const dog = new FpsWatchdog()
    run(dog, 0, 16_000, 16)
    expect(run(dog, 16_000, 21_000, 80).some(Boolean)).toBe(false) // one slow window
    expect(run(dog, 21_000, 60_000, 16).some(Boolean)).toBe(false)
  })

  it('ignores a window the page spent frozen or hidden', () => {
    const dog = new FpsWatchdog()
    run(dog, 0, 16_000, 16)
    dog.frame(16_100)
    expect(dog.frame(60_000)).toBe(false) // one frame in 44 s
    expect(dog.frame(60_016)).toBe(false)
  })

  it('starts again with a warm-up after a reset', () => {
    const dog = new FpsWatchdog()
    run(dog, 0, 30_000, 80)
    dog.reset()
    expect(run(dog, 30_000, 39_000, 80).some(Boolean)).toBe(false)
  })
})
