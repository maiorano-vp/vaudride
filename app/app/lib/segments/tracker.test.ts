import { describe, expect, it } from 'vitest'
import { SegmentTracker, segmentName, type Segment } from './tracker'

const climb: Segment = { id: 'a', name: { fr: 'Montée 1', en: 'Climb 1' }, start_m: 1000, end_m: 2000, category: 'cat4' }

/** Rides at 10 m/s, 1 s ticks, returning every completed segment. */
function ride(tracker: SegmentTracker, fromM: number, toM: number) {
  const results = []
  for (let d = fromM; d <= toM; d += 10) {
    const r = tracker.update(d, (d - fromM) / 10)
    if (r) results.push(r)
  }
  return results
}

describe('SegmentTracker', () => {
  it('times nothing after a jump into or across a segment, then works again', () => {
    const t = new SegmentTracker([climb])
    t.update(0, 0)
    expect(t.update(1500, 10)).toBeUndefined()
    expect(t.active).toBeUndefined()
    expect(t.update(1510, 11)).toBeUndefined()
    // jump back before the start, then ride it for real
    t.update(500, 20)
    const [result] = ride(t, 500, 2500)
    expect(result?.segment.id).toBe('a')
  })

  it('times a segment from the start line to the finish line', () => {
    const t = new SegmentTracker([climb])
    const [result] = ride(t, 0, 2500)
    expect(result?.segment.id).toBe('a')
    expect(result?.timeS).toBeCloseTo(100, 0) // 1000 m at 10 m/s
    expect(t.active).toBeUndefined()
  })

  it('exposes the run in progress', () => {
    const t = new SegmentTracker([climb])
    ride(t, 0, 1500)
    expect(t.active?.segment.id).toBe('a')
  })

  it('drops a run when the rider goes back before the start', () => {
    const t = new SegmentTracker([climb])
    ride(t, 0, 1500)
    t.update(900, 200)
    expect(t.active).toBeUndefined()
  })

  it('handles back-to-back segments and resets', () => {
    const next: Segment = { id: 'b', name: { fr: 'Suite' }, start_m: 2000, end_m: 3000, category: null }
    const t = new SegmentTracker([climb, next])
    expect(ride(t, 0, 3500).map((r) => r.segment.id)).toEqual(['a', 'b'])
    t.reset()
    expect(ride(t, 0, 2500).map((r) => r.segment.id)).toEqual(['a'])
  })

  it('names segments in the requested language, French as a fallback', () => {
    expect(segmentName(climb, 'en')).toBe('Climb 1')
    expect(segmentName(climb, 'de')).toBe('Montée 1')
  })
})
