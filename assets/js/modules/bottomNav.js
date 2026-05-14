import { config } from '../core/config.js'

/**
 * Bottom Navigation Module
 * Uses IntersectionObserver to highlight active section
 * Returns cleanup function for proper memory management
 */
export default function bottomNav(root) {
  try {
    const links = Array.from(root?.querySelectorAll('a[data-target]') ?? [])
    const sections = links
      .map((l) => document.querySelector(l.dataset.target))
      .filter(Boolean)

    if (!sections.length) return () => {}

    const obs = new IntersectionObserver(
      (entries) => {
        try {
          entries.forEach((e) => {
            if (e.isIntersecting) {
              const id = `#${e.target.id}`
              links.forEach((link) => {
                link?.classList?.toggle('active', link.dataset.target === id)
              })
            }
          })
        } catch (err) {
          console.error('IntersectionObserver callback error:', err)
        }
      },
      { threshold: config.observer.bottomNavThreshold }
    )

    sections.forEach((s) => obs.observe(s))

    // Return cleanup function
    return () => {
      try {
        obs.disconnect()
      } catch (e) {
        console.error('Failed to disconnect observer:', e)
      }
    }
  } catch (e) {
    console.error('Bottom navigation initialization error:', e)
    return () => {}
  }
}
