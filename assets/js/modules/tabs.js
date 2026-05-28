/**
 * Tabs Component
 * Manages tabbed content panels with proper ARIA attributes
 */
export default function initTabs(root) {
  try {
    const tabs = Array.from(root?.querySelectorAll('[role="tab"]') ?? [])
    const panels = Array.from(document.querySelectorAll('[data-panel]') ?? [])

    if (!tabs.length || !panels.length) return () => {}

    const cleanups = []

    /**
     * Activate a tab and its panel
     * @param {string} name - Tab name/identifier
     */
    const activate = (name) => {
      try {
        tabs.forEach((t) => {
          const isSelected = t.dataset.tab === name
          t.setAttribute('aria-selected', String(isSelected))
        })
        panels.forEach((p) => {
          p.classList.toggle('is-active', p.dataset.panel === name)
        })
      } catch (e) {
        console.error('Tab activation error:', e)
      }
    }

    // Attach click handlers to all tabs
    tabs.forEach((tab) => {
      try {
        const handler = () => {
          activate(tab.dataset.tab)
        }
        tab.addEventListener('click', handler)
        cleanups.push(() => tab.removeEventListener('click', handler))
      } catch (e) {
        console.error('Failed to attach tab listener:', e)
      }
    })

    return () => {
      cleanups.forEach((cleanup) => {
        try {
          cleanup()
        } catch (e) {
          console.error('Tab cleanup error:', e)
        }
      })
    }
  } catch (e) {
    console.error('Tabs initialization error:', e)
    return () => {}
  }
}
