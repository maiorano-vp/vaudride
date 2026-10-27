export type CourseActionName = 'gpx_download' | 'gpx_share' | 'transit_start' | 'transit_end' | 'map_start' | 'place_link' | 'lodging_link'

/**
 * Counts a step towards riding a course for real (a GPX taken, a transit link opened…) for Vaud Promotion's reports.
 * Anonymous: only the course and the kind of step are sent, no identifier. A failure changes nothing for the visitor.
 */
export function useCourseAction(slug: string) {
  function track(action: CourseActionName) {
    void $fetch(`/api/courses/${slug}/actions`, { method: 'POST', body: { action }, keepalive: true }).catch(() => {})
  }
  return { track }
}
