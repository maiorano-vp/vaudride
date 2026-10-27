/** Which of the sections (by id) the reader is on: the one crossing a band just under the sticky bar. */
export function useScrollSpy(ids: Ref<string[]>) {
  const active = ref<string>()
  let observer: IntersectionObserver | undefined

  function observe() {
    observer?.disconnect()
    observer = new IntersectionObserver(
      (entries) => {
        const inBand = entries
          .filter((e) => e.isIntersecting)
          .sort(
            (a, b) => a.boundingClientRect.top - b.boundingClientRect.top,
          )[0]
        if (inBand) active.value = inBand.target.id
      },
      // a band from just under the sticky bar to 40 % of the screen
      { rootMargin: '-96px 0px -60% 0px' },
    )
    for (const id of ids.value) {
      const el = document.getElementById(id)
      if (el) observer.observe(el)
    }
  }

  onMounted(() => {
    observe()
    watch(ids, () => nextTick(observe))
  })
  onBeforeUnmount(() => observer?.disconnect())

  return { active }
}
