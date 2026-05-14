/**
 * Event handling utilities with proper cleanup
 */

/**
 * Attach event listener with cleanup function
 * @param {Element} el - Target element
 * @param {string} event - Event name
 * @param {Function} handler - Event handler
 * @param {Object} opts - Event options
 * @returns {Function} Cleanup function
 */
export function on(el, event, handler, opts) {
  try {
    el?.addEventListener(event, handler, opts)
    return () => {
      try {
        el?.removeEventListener(event, handler, opts)
      } catch (e) {
        console.error(`Failed to remove event listener: ${event}`, e)
      }
    }
  } catch (e) {
    console.error(`Failed to attach event listener: ${event}`, e)
    return () => {}
  }
}

/**
 * Event delegation with error handling
 * @param {Element} root - Root element
 * @param {string} selector - CSS selector for delegated target
 * @param {string} event - Event name
 * @param {Function} handler - Event handler
 * @returns {Function} Cleanup function
 */
export function delegate(root, selector, event, handler) {
  const listener = (e) => {
    try {
      const target = e.target?.closest?.(selector)
      if (target) handler(e, target)
    } catch (err) {
      console.error(`Delegation handler error for ${selector}`, err)
    }
  }

  try {
    root?.addEventListener(event, listener)
    return () => {
      try {
        root?.removeEventListener(event, listener)
      } catch (e) {
        console.error(`Failed to remove delegated listener: ${event}`, e)
      }
    }
  } catch (e) {
    console.error(`Failed to setup delegation for ${selector}`, e)
    return () => {}
  }
}
