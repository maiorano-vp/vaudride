import { describe, expect, it } from 'vitest'
import {
  parseShow,
  passportProgress,
  stampDate,
  suggestStarters,
} from './passport'

const courses = [{ slug: 'lavaux' }, { slug: 'joux' }, { slug: 'mosses' }]
const ride = (
  slug: string,
  ridden_on: string,
  id = `${slug}-${ridden_on}`,
) => ({ id, ridden_on, course: { slug } })

describe('passportProgress', () => {
  it('splits the courses into those ridden for real and those left to discover', () => {
    const p = passportProgress(courses, [ride('joux', '2026-09-01')])
    expect(p.done.map((d) => d.course.slug)).toEqual(['joux'])
    expect(p.todo.map((c) => c.slug)).toEqual(['lavaux', 'mosses'])
    expect([p.count, p.total]).toEqual([1, 3])
  })

  it('counts a course once however many days it was ridden, the latest day first', () => {
    const p = passportProgress(courses, [
      ride('lavaux', '2026-05-01'),
      ride('lavaux', '2026-09-12'),
      ride('lavaux', '2026-07-03'),
    ])
    expect(p.count).toBe(1)
    expect(p.done[0]!.days).toEqual(['2026-09-12', '2026-07-03', '2026-05-01'])
  })

  it('ignores a declaration on a course that is not listed (any more)', () => {
    const p = passportProgress(courses, [ride('gone', '2026-09-01')])
    expect([p.count, p.total, p.done.length]).toEqual([0, 3, 0])
  })

  it('is empty without courses or declarations', () => {
    expect(passportProgress([], [])).toEqual({
      done: [],
      todo: [],
      count: 0,
      total: 0,
    })
  })
})

describe('parseShow', () => {
  it('keeps the two filters and shows everything otherwise', () => {
    expect(parseShow('done')).toBe('done')
    expect(parseShow('todo')).toBe('todo')
    expect(parseShow('all')).toBe('all')
    expect(parseShow('nonsense')).toBe('all')
    expect(parseShow(undefined)).toBe('all')
    expect(parseShow(['done'])).toBe('all')
  })
})

describe('stampDate', () => {
  it('writes the day as on a stamp, the same in every language', () => {
    expect(stampDate('2026-10-12')).toBe('12.10.26')
    expect(stampDate('2009-01-03')).toBe('03.01.09')
  })
})

describe('suggestStarters', () => {
  const c = (slug: string, distance_m: number, elevation_gain_m: number) => ({
    slug,
    distance_m,
    elevation_gain_m,
  })

  it('gives the shortest courses first, the flattest among equals, and no more than asked', () => {
    const todo = [
      c('long', 90_000, 1000),
      c('short-hilly', 10_000, 400),
      c('short-flat', 10_000, 50),
      c('mid', 30_000, 200),
    ]
    expect(suggestStarters(todo).map((x) => x.slug)).toEqual([
      'short-flat',
      'short-hilly',
      'mid',
    ])
    expect(suggestStarters(todo, 1).map((x) => x.slug)).toEqual(['short-flat'])
  })

  it('leaves the list it was given as it was', () => {
    const todo = [c('b', 2, 0), c('a', 1, 0)]
    suggestStarters(todo)
    expect(todo.map((x) => x.slug)).toEqual(['b', 'a'])
  })
})
