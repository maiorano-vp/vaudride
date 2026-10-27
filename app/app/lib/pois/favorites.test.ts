import { describe, expect, it } from 'vitest'
import { isFavoriteCommand, mergeFavorites, withFavorite } from './favorites'

describe('isFavoriteCommand', () => {
  it('hears the keyword in the rider’s language', () => {
    expect(isFavoriteCommand('ajoute ce lieu en favori', 'fr')).toBe(true)
    expect(isFavoriteCommand('das als Favorit merken', 'de')).toBe(true)
    expect(isFavoriteCommand('save it', 'en')).toBe(true)
  })

  it('ignores other speech', () => {
    expect(isFavoriteCommand('quelle belle vue', 'fr')).toBe(false)
    expect(isFavoriteCommand('favori', 'en')).toBe(false)
  })
})

describe('favorite ids', () => {
  it('adds an id once', () => {
    expect(withFavorite(['a'], 'b')).toEqual(['a', 'b'])
    expect(withFavorite(['a'], 'a')).toEqual(['a'])
  })

  it('merges without duplicates', () => {
    expect(mergeFavorites(['a', 'b'], ['b', 'c'])).toEqual(['a', 'b', 'c'])
  })
})
