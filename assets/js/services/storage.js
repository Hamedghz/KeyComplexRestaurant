import { config } from '../core/config.js'

/**
 * Storage Service
 * Manages localStorage with versioning, error handling, and migration support
 */
export const storage = {
  key: config.storageKey,

  /**
   * Get stored data with fallback
   * @returns {Object} Stored data or default
   */
  get() {
    try {
      const raw = localStorage.getItem(this.key)
      const data = raw ? JSON.parse(raw) : { version: 1, members: [] }
      return this.migrateIfNeeded(data)
    } catch (e) {
      console.error('Storage get error:', e)
      return { version: 1, members: [] }
    }
  },

  /**
   * Save data to storage
   * @param {Object} obj - Data to save
   * @returns {boolean} Success status
   */
  set(obj) {
    try {
      if (!obj || typeof obj !== 'object') {
        throw new Error('Invalid data object')
      }
      localStorage.setItem(this.key, JSON.stringify(obj))
      return true
    } catch (e) {
      console.error('Storage set error:', e)
      return false
    }
  },

  /**
   * Clear all stored data
   * @returns {boolean} Success status
   */
  clear() {
    try {
      localStorage.removeItem(this.key)
      return true
    } catch (e) {
      console.error('Storage clear error:', e)
      return false
    }
  },

  /**
   * Migrate data to new version if needed
   * @param {Object} data - Data to migrate
   * @returns {Object} Migrated data
   */
  migrateIfNeeded(data) {
    try {
      if (!data.version) {
        data.version = 1
      }
      // Add future migrations here
      return data
    } catch (e) {
      console.error('Storage migration error:', e)
      return { version: 1, members: [] }
    }
  }
}
