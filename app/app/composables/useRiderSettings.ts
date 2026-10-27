import { DEFAULT_SETTINGS, newest, sanitize, toParams, type RiderSettings } from '~/lib/rider/settings'

const KEY = 'vaudride.rider'
const OLD_FTP_KEY = 'vaudride.ftp' // before the settings had a page of their own, the FTP was kept alone
const SYNC_DELAY_MS = 800
/** The estimate is only suggested when it differs from the FTP in use by at least this share. */
const SUGGEST_FROM = 0.03

interface Stored {
  settings: RiderSettings
  /** When the rider last changed them (ISO); the copy changed last wins between this browser and the account. */
  at?: string
}

export interface FtpEstimate {
  ftp: number
  avg_power_w: number
  ride_id: string
  achieved_at: string
}

export type SyncState = 'idle' | 'saving' | 'saved' | 'error'

const readLocal = (): Stored | undefined => {
  try {
    const stored = JSON.parse(localStorage.getItem(KEY) ?? 'null')
    if (stored?.settings) return { settings: sanitize(stored.settings), at: typeof stored.at === 'string' ? stored.at : undefined }
    const ftp = Number(JSON.parse(localStorage.getItem(OLD_FTP_KEY) ?? 'null'))
    return ftp ? { settings: sanitize({ ...DEFAULT_SETTINGS, ftp }) } : undefined
  } catch {
    return undefined // storage blocked or corrupted: start from the defaults
  }
}
const writeLocal = (value: Stored) => {
  try {
    localStorage.setItem(KEY, JSON.stringify(value))
  } catch {
    // private mode: the settings just do not survive the page (the account still has them)
  }
}

let pushTimer: ReturnType<typeof setTimeout> | undefined

/**
 * The rider's weight, position, FTP and difficulty, shared by the settings page, the pause menu and the ride.
 * Kept in this browser; for a signed-in player they also follow the account, so every device has them.
 */
export function useRiderSettings() {
  const { user } = useAuth()
  const settings = useState<RiderSettings>('rider-settings', () => DEFAULT_SETTINGS)
  const changedAt = useState<string | undefined>('rider-settings-at', () => undefined)
  const sync = useState<SyncState>('rider-settings-sync', () => 'idle')
  /** undefined: not asked yet; null: asked, no 20-minute effort in the rides. */
  const estimate = useState<FtpEstimate | null | undefined>('rider-ftp-estimate', () => undefined)

  const params = computed(() => toParams(settings.value))
  const ftp = computed(() => settings.value.ftp)
  const difficultyPct = computed(() => settings.value.difficultyPct)
  /** An FTP worth suggesting: the estimate from the rides, when it is not (nearly) the one already in use. */
  const ftpSuggestion = computed(() => {
    const e = estimate.value
    return e && Math.abs(e.ftp - settings.value.ftp) / settings.value.ftp >= SUGGEST_FROM ? e : undefined
  })

  async function push() {
    sync.value = 'saving'
    try {
      const res = await $fetch<{ updated_at: string }>('/api/me/settings', { method: 'PUT', body: settings.value })
      changedAt.value = res.updated_at
      writeLocal({ settings: settings.value, at: res.updated_at })
      sync.value = 'saved'
    } catch {
      sync.value = 'error' // kept here; sent again with the next change or the next visit
    }
  }
  function schedulePush() {
    if (!user.value) return
    sync.value = 'saving'
    clearTimeout(pushTimer)
    pushTimer = setTimeout(push, SYNC_DELAY_MS)
  }

  /** Reads this browser's copy, then the account's: the most recently changed one is kept and the other is brought up to date. */
  async function load() {
    const local = readLocal()
    if (local) {
      settings.value = local.settings
      changedAt.value = local.at
    }
    if (!user.value) return

    const remote = await $fetch<{ data: unknown; updated_at: string | null }>('/api/me/settings').catch(() => undefined)
    if (remote?.data) {
      const winner = newest(local && { at: local.at }, { at: remote.updated_at ?? undefined })
      if (winner === 'remote' || winner === 'none') {
        settings.value = sanitize(remote.data)
        changedAt.value = remote.updated_at ?? undefined
        writeLocal({ settings: settings.value, at: changedAt.value })
      } else {
        await push()
      }
    } else if (remote && local) {
      await push() // the account has none yet: this browser's become the account's
    }
    void loadEstimate()
  }

  async function loadEstimate() {
    if (!user.value) return
    estimate.value = await $fetch<{ data: FtpEstimate | null }>('/api/me/ftp-estimate')
      .then((r) => r.data)
      .catch(() => undefined)
  }

  function update(patch: Partial<RiderSettings>) {
    settings.value = sanitize({ ...settings.value, ...patch })
    changedAt.value = new Date().toISOString()
    writeLocal({ settings: settings.value, at: changedAt.value })
    schedulePush()
  }
  const reset = () => update(DEFAULT_SETTINGS)
  /** Takes the estimate from the rides as the FTP. */
  const useEstimate = () => ftpSuggestion.value && update({ ftp: ftpSuggestion.value.ftp })

  return { settings, params, ftp, difficultyPct, sync, estimate, ftpSuggestion, load, loadEstimate, update, reset, useEstimate }
}
