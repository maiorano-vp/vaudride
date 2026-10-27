/**
 * The dropdowns of the header: the white card of vaud.ch's language selector (soft shadow, square corners, no border).
 * Two kinds: a short list of capitals (the language), and a longer, grouped list that is easy to read (the user menu).
 */
const CARD =
  'rounded-none bg-white shadow-[0_2px_12px_rgba(10,51,77,0.08)] ring-0'

export const languageMenuUi = {
  content: `min-w-20 ${CARD}`,
  viewport: 'divide-y-0',
  group: 'px-4 py-2',
  item: 'rounded-none px-0 py-0 before:rounded-none data-highlighted:before:bg-transparent',
  itemLabel:
    'font-display text-[1.375rem] uppercase leading-7 text-vaud-800 group-data-highlighted:text-vaud-green-600',
}

export const userMenuUi = {
  content: `min-w-72 ${CARD}`,
  viewport: 'divide-y divide-[#e6ecf0]',
  group: 'p-2',
  label:
    'px-3 pt-2 pb-1 font-display text-sm font-normal uppercase tracking-[0.08em] text-vaud-600',
  item: 'rounded-none px-3 py-2.5 before:rounded-none data-highlighted:before:bg-[#f2f5f7]',
  itemLeadingIcon: 'size-5 text-vaud-600',
  itemLabel: 'font-sans text-base normal-case text-vaud-950',
}
