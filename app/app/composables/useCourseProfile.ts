import type { CourseProfile } from '~/lib/course/profile'

/**
 * The course profile, loaded in the browser only: a Worker fetching its own public URL during SSR fails intermittently.
 * The API serves it (uploaded in the admin); the bundled static file is the fallback while a course has none.
 */
export function useCourseProfile(slug: string) {
  return useAsyncData(
    `course-${slug}`,
    async () => {
      try {
        return await $fetch<CourseProfile>(`/api/courses/${slug}/profile`, { retry: 2 })
      } catch {
        return await $fetch<CourseProfile>(`/courses/${slug}/profile.json`, { retry: 2 })
      }
    },
    { server: false },
  )
}
