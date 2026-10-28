import { fileSlug, toGpx } from '~/lib/course/gpx'

interface GpxSource {
  attribution: string
  points: Array<{ lat: number; lon: number; ele: number }>
}

// Chrome on Android only shares an allow-listed set of MIME types (no application/gpx+xml); text/xml passes, the .gpx name stays.
const SHARE_TYPE = 'text/xml'

/** The course as a GPX file: downloaded, or sent to another app (Komoot, Garmin…) through the phone's share sheet. */
export function useGpx(profile: Ref<GpxSource | null | undefined>, name: Ref<string>) {
  // known once mounted, so the server and the first client render agree
  const canShare = ref(false)
  onMounted(() => {
    const probe = new File([''], 'probe.gpx', { type: SHARE_TYPE })
    canShare.value = typeof navigator.canShare === 'function' && navigator.canShare({ files: [probe] })
  })

  const file = (source: GpxSource, type = 'application/gpx+xml') =>
    new File([toGpx({ name: name.value, attribution: source.attribution, points: source.points })], `${fileSlug(name.value)}.gpx`, {
      type,
    })

  function download() {
    if (!profile.value) return
    const url = URL.createObjectURL(file(profile.value))
    const a = document.createElement('a')
    a.href = url
    a.download = `${fileSlug(name.value)}.gpx`
    a.click()
    URL.revokeObjectURL(url)
  }

  /** True once the share sheet took the file; false if it was dismissed, failed (downloads instead) or is not available. */
  async function share(): Promise<boolean> {
    if (!profile.value || !canShare.value) return false
    try {
      await navigator.share({ files: [file(profile.value, SHARE_TYPE)], title: name.value })
      return true
    } catch (e) {
      if ((e as DOMException).name === 'AbortError') return false // dismissed
      download() // the share sheet refused the file: still hand it over
      return false
    }
  }

  return { canShare, download, share }
}
