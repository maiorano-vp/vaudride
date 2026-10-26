export default defineAppConfig({
  ui: {
    colors: { primary: 'vaud', success: 'vaud-green', neutral: 'slate' },
    // vaud.ch buttons: navy, square-ish corners, generous padding, condensed uppercase
    button: {
      slots: { base: 'rounded-sm font-display uppercase tracking-wide' },
      variants: {
        size: {
          md: { base: 'px-4 py-2 text-base' },
          lg: { base: 'px-6 py-3 text-lg' },
          xl: { base: 'px-6 py-3 text-lg' },
        },
      },
      defaultVariants: { size: 'lg' },
    },
  },
})
