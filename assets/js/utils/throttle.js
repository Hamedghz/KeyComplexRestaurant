/**
 * Throttle utility
 * Ensures function executes at most once per specified interval
 * Useful for high-frequency events like scroll and resize
 */

/**
 * Create a throttled version of a function
 * @param {Function} fn - Function to throttle
 * @param {number} limit - Time limit in milliseconds (default: 100)
 * @returns {Function} Throttled function
 */
export function throttle(fn, limit = 100) {
  let lastTime = 0

  return function throttled(...args) {
    try {
      const now = Date.now()
      if (now - lastTime >= limit) {
        lastTime = now
        fn.apply(this, args)
      }
    } catch (e) {
      console.error('Throttle error:', e)
    }
  }
}
