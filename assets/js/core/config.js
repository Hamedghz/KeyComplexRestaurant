/**
 * Application configuration
 * Centralized settings for all modules
 */
export const config = {
  carousel: {
    autoplay: 6000,
    transition: 400,
    touchThreshold: 40 // px
  },
  storageKey: 'webland:v1',
  form: {
    phoneRegex: /^09\d{9}$/,
    phoneRegexIntl: /^\+?[1-9]\d{1,14}$/ // E.164 format
  },
  observer: {
    bottomNavThreshold: 0.6
  },
  debug: false // Set to true for console logging
}
