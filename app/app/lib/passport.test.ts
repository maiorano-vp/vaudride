import { describe, expect, it } from 'vitest'
import { passportProgress } from './passport'

const courses = [{ slug: 'lavaux' }, { slug: 'joux' }, { slug: 'mosses' }]
const ride = (slug: string, ridden_on: string, id = `${slug}-${ridden_on}`) => ({ id, ridden_on, course: { slug } })

describe('passportProgress', () => {
  it('splits the courses into those ridden for real and those left to discover', () => {
    const p = passportProgress(courses, [ride('joux', '2026-09-01')])
    expect(p.done.map((d) => d.course.slug)).toEqual(['joux'])
    expect(p.todo.map((c) => c.slug)).toEqual(['lavaux', 'mosses'])
    expect([p.count, p.total]).toEqual([1, 3])
  })

  it('counts a course once however many days it was ridden, the latest day first', () => {
    const p = passportProgress(courses, [ride('lavaux', '2026-05-01'), ride('lavaux', '2026-09-12'), ride('lavaux', '2026-07-03')])
    expect(p.count).toBe(1)
    expect(p.done[0]!.days).toEqual(['2026-09-12', '2026-07-03', '2026-05-01'])
  })

  it('ignores a declaration on a course that is not listed (any more)', () => {
    const p = passportProgress(courses, [ride('gone', '2026-09-01')])
    expect([p.count, p.total, p.done.length]).toEqual([0, 3, 0])
  })

  it('is empty without courses or declarations', () => {
    expect(passportProgress([], [])).toEqual({ done: [], todo: [], count: 0, total: 0 })
  })
})
