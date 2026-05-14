import { config } from './core/config.js'
import Carousel from './modules/carousel.js'
import bottomNav from './modules/bottomNav.js'
import initTabs from './modules/tabs.js'
import initClubForm from './modules/form.js'

/**
 * Main Application Initializer
 * Initializes all modules and manages cleanup
 */
class App {
  constructor() {
    this.cleanups = [] // Track cleanup functions
    this.carouselInstance = null
    this.init()
  }

  init() {
    try {
      document.addEventListener('DOMContentLoaded', () => this.onDOMReady())

      // Also run if DOM is already loaded
      if (document.readyState === 'loading') {
        return
      }
      this.onDOMReady()
    } catch (e) {
      console.error('App initialization error:', e)
    }
  }

  onDOMReady() {
    try {
      // Header scroll effect
      this.initHeaderScroll()

      // Initialize Carousel
      this.initCarousel()

      // Initialize Bottom Navigation
      this.initBottomNav()

      // Initialize Tabs
      this.initTabs()

      // Initialize Club Form
      this.initForm()

      // Setup page unload cleanup
      window.addEventListener('beforeunload', () => this.destroy())
    } catch (e) {
      console.error('DOM ready initialization error:', e)
    }
  }

  /**
   * Header scroll effect
   */
  initHeaderScroll() {
    try {
      const header = document.querySelector('.header')
      if (!header) return

      const handleScroll = () => {
        header?.classList?.toggle('is-scrolled', window.scrollY > 8)
      }

      window.addEventListener('scroll', handleScroll, { passive: true })
      this.cleanups.push(() => {
        window.removeEventListener('scroll', handleScroll)
      })
    } catch (e) {
      console.error('Header scroll initialization error:', e)
    }
  }

  /**
   * Initialize Carousel
   */
  initCarousel() {
    try {
      const carEl = document.querySelector('[data-carousel]')
      if (!carEl) return

      this.carouselInstance = new Carousel(carEl, {
        autoplay: config.carousel.autoplay
      })

      // Add cleanup
      this.cleanups.push(() => {
        this.carouselInstance?.destroy?.()
      })
    } catch (e) {
      console.error('Carousel initialization error:', e)
    }
  }

  /**
   * Initialize Bottom Navigation
   */
  initBottomNav() {
    try {
      const bn = document.querySelector('[data-bottom-nav]')
      if (!bn) return

      const cleanup = bottomNav(bn)
      this.cleanups.push(cleanup)
    } catch (e) {
      console.error('Bottom nav initialization error:', e)
    }
  }

  /**
   * Initialize Tabs
   */
  initTabs() {
    try {
      const tabs = document.querySelector('[data-tabs]')
      if (!tabs) return

      initTabs(tabs)
    } catch (e) {
      console.error('Tabs initialization error:', e)
    }
  }

  /**
   * Initialize Club Form
   */
  initForm() {
    try {
      const form = document.querySelector('[data-club-form]')
      if (!form) return

      initClubForm(form)
    } catch (e) {
      console.error('Form initialization error:', e)
    }
  }

  /**
   * Cleanup and destroy
   */
  destroy() {
    try {
      this.cleanups.forEach((cleanup) => {
        try {
          cleanup()
        } catch (e) {
          console.error('Cleanup error:', e)
        }
      })
      this.cleanups = []
    } catch (e) {
      console.error('App destroy error:', e)
    }
  }
}

// Initialize app
const app = new App()
