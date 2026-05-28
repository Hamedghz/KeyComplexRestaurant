/**
 * Validation utilities
 * Commonly used validators for form and data validation
 */

export const validators = {
  /**
   * Check if value is not empty
   * @param {*} v - Value to check
   * @returns {boolean}
   */
  required: (v) =>
    v !== undefined && v !== null && String(v).trim() !== '',

  /**
   * Validate email format
   * @param {string} v - Email to validate
   * @returns {boolean}
   */
  email: (v) => /\S+@\S+\.\S+/.test(String(v ?? '')),

  /**
   * Validate Iranian phone number
   * @param {string} v - Phone number to validate
   * @returns {boolean}
   */
  iranianPhone: (v) => /^09\d{9}$/.test(String(v ?? '')),

  /**
   * Validate international phone number (E.164)
   * @param {string} v - Phone number to validate
   * @returns {boolean}
   */
  internationalPhone: (v) => /^\+?[1-9]\d{1,14}$/.test(String(v ?? '')),

  /**
   * Validate URL format
   * @param {string} v - URL to validate
   * @returns {boolean}
   */
  url: (v) => {
    try {
      new URL(v)
      return true
    } catch {
      return false
    }
  },

  /**
   * Validate number range
   * @param {number} v - Value to check
   * @param {number} min - Minimum value
   * @param {number} max - Maximum value
   * @returns {boolean}
   */
  range: (v, min, max) => {
    const num = Number(v)
    return !isNaN(num) && num >= min && num <= max
  }
}
