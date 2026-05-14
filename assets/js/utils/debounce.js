/**
 * Debounce utility
 * Delays function execution until after specified wait time has passed
 * Useful for resize, scroll, and input events
 */

/**
 * Create a debounced version of a function
 * @param {Function} fn - Function to debounce
 * @param {number} wait - Wait time in milliseconds (default: 100)
 * @returns {Function} Debounced function
 */
export function debounce(fn, wait = 100) {
  let timeoutId

  return function debounced(...args) {
    try {
      clearTimeout(timeoutId)
      timeoutId = setTimeout(() => {
        fn.apply(this, args)
      }, wait)
    } catch (e) {
      console.error('Debounce error:', e)
    }
  }
}
