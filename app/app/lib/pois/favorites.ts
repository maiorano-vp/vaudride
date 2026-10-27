/** Words that mean "keep this place", by app locale. */
const FAVORITE_WORDS: Record<string, RegExp> = {
  fr: /\b(favori|favoris|j'aime|garde (ça|ce lieu)|enregistre)\b/i,
  de: /\b(favorit|favoriten|merken|speichern|gefällt mir)\b/i,
  en: /\b(favou?rite|save (it|this)|i like (it|this)|bookmark)\b/i,
}

export const isFavoriteCommand = (transcript: string, locale: string): boolean =>
  (FAVORITE_WORDS[locale] ?? FAVORITE_WORDS.en!).test(transcript)

/** The ids with `id` added, once. */
export const withFavorite = (ids: string[], id: string): string[] => (ids.includes(id) ? ids : [...ids, id])

/** The ids of both lists, without duplicates. */
export const mergeFavorites = (a: string[], b: string[]): string[] => [...new Set([...a, ...b])]
