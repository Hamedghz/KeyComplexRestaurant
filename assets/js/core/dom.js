/**
 * DOM helper utilities for common operations
 */

/**
 * Select single element
 * @param {string} selector - CSS selector
 * @param {Element} ctx - Context element (default: document)
 * @returns {Element|null}
 */
export const $ = (selector, ctx = document) => {
  try {
    return ctx.querySelector(selector)
  } catch (e) {
    console.error(`DOM Error in selector: ${selector}`, e)
    return null
  }
}

/**
 * Select multiple elements
 * @param {string} selector - CSS selector
 * @param {Element} ctx - Context element (default: document)
 * @returns {Array<Element>}
 */
export const $$ = (selector, ctx = document) => {
  try {
    return Array.from(ctx.querySelectorAll(selector))
  } catch (e) {
    console.error(`DOM Error in selector: ${selector}`, e)
    return []
  }
}

/**
 * Create element with attributes
 * @param {string} tag - HTML tag name
 * @param {Object} attrs - Attributes object
 * @returns {Element}
 */
export function createEl(tag, attrs = {}) {
  try {
    const el = document.createElement(tag)
    Object.entries(attrs).forEach(([k, v]) => {
      el.setAttribute(k, v)
    })
    return el
  } catch (e) {
    console.error(`Failed to create element: ${tag}`, e)
    return null
  }
}

/**
 * Safely remove element
 * @param {Element} el - Element to remove
 * @returns {boolean}
 */
export function removeEl(el) {
  try {
    el?.parentNode?.removeChild(el)
    return true
  } catch (e) {
    console.error('Failed to remove element', e)
    return false
  }
}
