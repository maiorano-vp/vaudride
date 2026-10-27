/** Where a recorded rider was: [seconds since the start, metres along the course], increasing in both. */
export type GhostSamples = Array<[number, number]>

export type GhostKind = 'best' | 'last' | 'kom'

/** Distance (m) after `t` seconds, interpolated between samples; before the first sample the rider is at the first, after the last at the last. */
export function distanceAt(samples: GhostSamples, t: number): number {
  const first = samples[0]
  const last = samples[samples.length - 1]
  if (!first || !last) return 0
  if (t <= first[0]) return first[1]
  if (t >= last[0]) return last[1]
  let lo = 0
  let hi = samples.length - 1
  while (hi - lo > 1) {
    const mid = (lo + hi) >> 1
    if (samples[mid]![0] <= t) lo = mid
    else hi = mid
  }
  const [t0, d0] = samples[lo]!
  const [t1, d1] = samples[hi]!
  return t1 === t0 ? d1 : d0 + ((d1 - d0) * (t - t0)) / (t1 - t0)
}

/** Seconds after the start at which the ghost reached `distanceM`; undefined if it never did. */
export function timeAtDistance(samples: GhostSamples, distanceM: number): number | undefined {
  const last = samples[samples.length - 1]
  if (!last || distanceM > last[1]) return undefined
  for (let i = 0; i < samples.length; i++) {
    const [t1, d1] = samples[i]!
    if (d1 < distanceM) continue
    const prev = samples[i - 1]
    if (!prev || d1 === prev[1]) return t1
    return prev[0] + ((t1 - prev[0]) * (distanceM - prev[1])) / (d1 - prev[1])
  }
  return undefined
}

/** Speed (m/s) of the ghost around `t`, for the pedalling animation. */
export function speedAt(samples: GhostSamples, t: number): number {
  return Math.max(0, distanceAt(samples, t + 1) - distanceAt(samples, t))
}

/**
 * Where the ghost of a segment record is. It waits on the start line until the rider crosses it, then rides on the
 * rider's segment clock. After the finish it stays on the line until the rider is 100 m past it, then disappears.
 *
 * @param runS Seconds since the rider crossed the start line; undefined when not on the segment
 */
export function recordGhostDistance(
  samples: GhostSamples,
  segment: { start_m: number; end_m: number },
  riderM: number,
  runS: number | undefined,
): number | undefined {
  if (runS !== undefined) return distanceAt(samples, runS)
  if (riderM < segment.start_m) return segment.start_m
  if (riderM < segment.end_m + 100) return segment.end_m
  return undefined
}

/** Whole-course ghosts as the API sends them (`best` and `last` are null when the player has no finished ride yet). */
export interface CourseGhosts {
  best: { duration_s: number; samples: GhostSamples } | null
  last: { duration_s: number; samples: GhostSamples } | null
}
