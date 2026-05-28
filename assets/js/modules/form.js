import { storage } from '../services/storage.js'
import { config } from '../core/config.js'

/**
 * Club Form Module
 * Handles form submission, validation, and member registration
 * Includes input sanitization and error handling
 */

/**
 * Escape HTML special characters to prevent XSS
 * @param {string} str - String to escape
 * @returns {string} Escaped string
 */
function escapeHtml(str) {
  const div = document.createElement('div')
  div.textContent = str
  return div.innerHTML
}

/**
 * Validate phone number
 * @param {string} phone - Phone number to validate
 * @returns {boolean}
 */
function validatePhone(phone) {
  const trimmed = phone?.trim?.() ?? ''
  // Check Iranian format or E.164 international format
  return (
    config.form.phoneRegex.test(trimmed) ||
    config.form.phoneRegexIntl.test(trimmed)
  )
}

/**
 * Initialize club membership form
 * @param {HTMLElement} form - Form element
 */
export default function initClubForm(form) {
  try {
    const message = form?.querySelector('[data-form-message]')

    if (!form || !message) {
      console.warn('Club form elements not found')
      return
    }

    form.addEventListener('submit', (e) => {
      e.preventDefault()

      try {
        const phone = form.phone?.value?.trim?.() ?? ''

        // Validate input
        if (!phone) {
          message.textContent = 'لطفاً شماره موبایل را وارد کنید'
          return
        }

        if (!validatePhone(phone)) {
          message.textContent = 'شماره موبایل معتبر نیست'
          return
        }

        // Get storage data with safety checks
        const data = storage.get()
        if (!data.members) {
          data.members = []
        }

        // Add new member with escaped phone number
        data.members.push({
          phone: escapeHtml(phone),
          at: Date.now()
        })

        // Save safely
        storage.set(data)

        // Success message
        message.textContent = '✓ عضویت شما ثبت شد'
        message.style.color = 'green'
        form.reset()

        // Reset message after 3 seconds
        setTimeout(() => {
          try {
            message.textContent = ''
            message.style.color = ''
          } catch (err) {
            console.error('Error resetting message:', err)
          }
        }, 3000)
      } catch (err) {
        console.error('Form submission error:', err)
        message.textContent = 'خطایی در ثبت‌نام رخ داد'
        message.style.color = 'red'
      }
    })
  } catch (e) {
    console.error('Club form initialization error:', e)
  }
}
