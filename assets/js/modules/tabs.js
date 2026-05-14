/**
 * Tabs Component
 * Manages tabbed content panels with proper ARIA attributes
 */
export default function initTabs(root) {
  try {
    const tabs = Array.from(root?.querySelectorAll('[role="tab"]') ?? [])
    const panels = Array.from(document.querySelectorAll('[data-panel]') ?? [])

    if (!tabs.length || !panels.length) return

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
        tab.addEventListener('click', () => {
          activate(tab.dataset.tab)
        })
      } catch (e) {
        console.error('Failed to attach tab listener:', e)
      }
    })
  } catch (e) {
    console.error('Tabs initialization error:', e)
  }
}
