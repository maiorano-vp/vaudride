import { describe, expect, it } from 'vitest'
import { distanceAt, dropStale, remove, upsert } from './riders'
import { simLiveTransport } from './sim'

const msg = (d: number) => ({ id: 'a', name: 'A', d, p: 200 })

describe('riders', () => {
  it('derives speed from two messages and extrapolates, capped at 2 s', () => {
    const list = upsert(upsert([], msg(0), 0), msg(10), 1000)
    expect(list[0]!.speedMs).toBe(10)
    expect(distanceAt(list[0]!, 1500)).toBe(15)
    expect(distanceAt(list[0]!, 9000)).toBe(30)
  })
  it('removes left and stale riders', () => {
    const list = upsert([], msg(0), 0)
    expect(remove(list, 'a')).toEqual([])
    expect(dropStale(list, 10_001)).toEqual([])
    expect(dropStale(list, 5_000)).toHaveLength(1)
  })
})

describe('sim transport', () => {
  it('emits fake riders until closed', async () => {
    const seen: string[] = []
    const room = simLiveTransport(5).join('x', { onMessage: (m) => seen.push(m.id), onLeave() {} })
    await new Promise((r) => setTimeout(r, 20))
    room.close()
    const n = seen.length
    await new Promise((r) => setTimeout(r, 20))
    expect(n).toBeGreaterThan(0)
    expect(seen).toHaveLength(n)
  })
})
