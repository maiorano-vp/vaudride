import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

type Tree = Record<string, unknown>

const load = (locale: string): Tree => JSON.parse(readFileSync(new URL(`../i18n/locales/${locale}.json`, import.meta.url), 'utf8'))
const at = (tree: Tree, key: string): unknown => key.split('.').reduce<unknown>((node, part) => (node as Tree)[part], tree)

/** Every key of a locale file, nested ones as "a.b.c". */
const keys = (tree: Tree, prefix = ''): string[] =>
  Object.entries(tree).flatMap(([k, v]) => (v && typeof v === 'object' ? keys(v as Tree, `${prefix}${k}.`) : [`${prefix}${k}`]))

describe('locale files', () => {
  const fr = load('fr')
  const frKeys = keys(fr).sort()

  it.each(['de', 'en'])('%s has exactly the keys of the French file (the reference)', (locale) => {
    const other = keys(load(locale)).sort()
    expect(frKeys.filter((k) => !other.includes(k)), `missing in ${locale}`).toEqual([])
    expect(other.filter((k) => !frKeys.includes(k)), 'not in fr').toEqual([])
  })

  it.each(['de', 'en'])('%s has no empty text except where French is empty on purpose', (locale) => {
    const text = load(locale)
    const empty = (tree: Tree) => keys(tree).filter((k) => at(tree, k) === '').sort()
    expect(empty(text)).toEqual(empty(fr))
  })

  it.each(['de', 'en'])('%s keeps the same {placeholders} as French', (locale) => {
    const text = load(locale)
    const placeholders = (tree: Tree, key: string) => String(at(tree, key)).match(/\{\w+\}/g)?.sort() ?? []
    expect(frKeys.filter((k) => placeholders(fr, k).join() !== placeholders(text, k).join())).toEqual([])
  })
})
