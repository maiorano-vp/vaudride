export interface Segment {
  id: string
  name: Record<string, string>
  start_m: number
  end_m: number
  category: string | null
}

export interface SegmentRun {
  segment: Segment
  /** Ride clock (s) when the rider crossed the start. */
  startedAtS: number
}

export interface SegmentResult {
  segment: Segment
  timeS: number
}

/** The segment's name in `locale`, falling back to French. */
export const segmentName = (s: Segment, locale: string) => s.name[locale] || s.name.fr || ''

/**
 * Times the rider on segments: a run starts when the start line is crossed and ends at the finish line.
 * Starting in the middle of a segment (or going back before its start) times nothing.
 */
export class SegmentTracker {
  private previousM = 0
  active?: SegmentRun

  constructor(private readonly segments: Segment[]) {}

  /** Feeds the latest position and ride clock; returns the segment that was just completed, if any. */
  update(distanceM: number, elapsedS: number): SegmentResult | undefined {
    const previousM = this.previousM
    this.previousM = distanceM
    let result: SegmentResult | undefined

    if (this.active) {
      const { segment, startedAtS } = this.active
      if (distanceM < segment.start_m) this.active = undefined
      else if (distanceM >= segment.end_m) {
        result = { segment, timeS: elapsedS - startedAtS }
        this.active = undefined
      }
    }

    if (!this.active) {
      const crossed = this.segments.find((s) => previousM <= s.start_m && s.start_m <= distanceM && distanceM < s.end_m)
      if (crossed) this.active = { segment: crossed, startedAtS: elapsedS }
    }
    return result
  }

  reset() {
    this.previousM = 0
    this.active = undefined
  }
}
