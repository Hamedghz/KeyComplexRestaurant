import { config } from '../core/config.js'

/**
 * Carousel/Slider Component
 * Handles image rotation with autoplay, touch, keyboard, and mouse support
 * Includes proper memory cleanup and lazy loading
 */
export default class Carousel {
  /**
   * @param {HTMLElement} root - Carousel container element
   * @param {Object} opts - Configuration options
   * @param {number} opts.autoplay - Autoplay interval in ms (default: 4000)
   */
  constructor(root, opts = {}) {
    this.root = root
    this.track = root?.querySelector('.carousel-track')
    this.slides = Array.from(root?.querySelectorAll('.carousel-slide') ?? [])
    this.prevBtn = root?.querySelector('.carousel-prev')
    this.nextBtn = root?.querySelector('.carousel-next')
    this.dots = Array.from(root?.querySelectorAll('[data-dot]') ?? [])
    this.index = 0
    this.count = this.slides.length
    this.autoplay = opts.autoplay ?? config.carousel.autoplay
    this.timer = null
    this.isPaused = false
    this.touch = { startX: 0, deltaX: 0 }
    this.cleanups = [] // Track cleanup functions

    if (this.root) {
      this.init()
    }
  }

  /**
   * Initialize carousel
   */
  init() {
    if (!this.track || this.count === 0) return

    try {
      this.update()
      this.bind()
      this.start()
      this.lazyLoadVisible()
    } catch (e) {
      console.error('Carousel initialization error:', e)
    }
  }


    /**
   * Bind all event listeners
   */
  bind() {
    try {
      // Navigation buttons
      if (this.nextBtn) {
        const nextCleanup = this._addEventListener(
          this.nextBtn,
          'click',
          () => this.goTo(this.index + 1)
        )
        this.cleanups.push(nextCleanup)
      }

      if (this.prevBtn) {
        const prevCleanup = this._addEventListener(
          this.prevBtn,
          'click',
          () => this.goTo(this.index - 1)
        )
        this.cleanups.push(prevCleanup)
      }

      // Dots navigation
      this.dots.forEach((dot) => {
        const cleanup = this._addEventListener(dot, 'click', () => {
          this.goTo(Number(dot.dataset.dot))
        })
        this.cleanups.push(cleanup)
      })

      // Pause on hover/focus
      this.cleanups.push(
        this._addEventListener(this.root, 'mouseenter', () => this.pause())
      )
      this.cleanups.push(
        this._addEventListener(this.root, 'focusin', () => this.pause())
      )
      this.cleanups.push(
        this._addEventListener(this.root, 'mouseleave', () => this.resume())
      )
      this.cleanups.push(
        this._addEventListener(this.root, 'focusout', () => this.resume())
      )

      // Keyboard navigation with RTL awareness
      this.cleanups.push(
        this._addEventListener(this.root, 'keydown', (e) => {
          const isRTL = document?.documentElement?.dir === 'rtl'

          if (e.key === 'ArrowRight') {
            this.goTo(isRTL ? this.index - 1 : this.index + 1)
          }

          if (e.key === 'ArrowLeft') {
            this.goTo(isRTL ? this.index + 1 : this.index - 1)
          }
        })
      )

      // Touch navigation
      this.cleanups.push(
        this._addEventListener(
          this.root,
          'touchstart',
          (e) => {
            this.touch.startX = e.touches?.[0]?.clientX ?? 0
          },
          { passive: true }
        )
      )

      this.cleanups.push(
        this._addEventListener(
          this.root,
          'touchmove',
          (e) => {
            this.touch.deltaX =
              (e.touches?.[0]?.clientX ?? 0) - this.touch.startX
          },
          { passive: true }
        )
      )

      this.cleanups.push(
        this._addEventListener(this.root, 'touchend', () => {
          const threshold = config.carousel.touchThreshold
          if (this.touch.deltaX > threshold) {
            this.goTo(this.index - 1)
          } else if (this.touch.deltaX < -threshold) {
            this.goTo(this.index + 1)
          }
          this.touch.deltaX = 0
        })
      )

      // Resize handler
      const resizeCleanup = this._addEventListener(
        window,
        'resize',
        () => this.update(),
        { passive: true }
      )
      this.cleanups.push(resizeCleanup)

      // Set ARIA attributes
      this.root.setAttribute('role', 'region')
      this.root.setAttribute('aria-label', 'اسلایدر تصاویر')
      this.root.setAttribute('tabindex', '0')
    } catch (e) {
      console.error('Carousel bind error:', e)
    }
  }

  /**
   * Helper to attach event with error handling
   * @private
   */
  _addEventListener(el, event, handler, opts) {
    try {
      el?.addEventListener(event, handler, opts)
      return () => {
        try {
          el?.removeEventListener(event, handler, opts)
        } catch (e) {
          console.error(`Failed to remove ${event} listener`, e)
        }
      }
    } catch (e) {
      console.error(`Failed to attach ${event} listener`, e)
      return () => {}
    }
  }

  /**
   * Update carousel position and state
   */
  update() {
    try {
      const x = -this.index * 100
      this.track?.style?.setProperty(
        'transform',
        `translate3d(${x}%,0,0)`
      )

      this.slides.forEach((slide, i) => {
        slide?.setAttribute('aria-hidden', String(i !== this.index))
      })

      this.dots.forEach((dot, i) => {
        const isActive = i === this.index
        dot?.classList?.toggle('is-active', isActive)
        dot?.setAttribute('aria-current', isActive ? 'true' : 'false')
      })
    } catch (e) {
      console.error('Carousel update error:', e)
    }
  }

  /**
   * Navigate to slide index
   * @param {number} i - Slide index
   */
  goTo(i) {
    try {
      this.index = (i + this.count) % this.count
      this.update()
      this.lazyLoadVisible()
      this.prefetchNext()
      this.restart()
    } catch (e) {
      console.error('Carousel navigation error:', e)
    }
  }

  /**
   * Start autoplay
   */
  start() {
    if (this.autoplay && !this.timer) {
      try {
        this.timer = setInterval(() => {
          if (!this.isPaused) {
            this.goTo(this.index + 1)
          }
        }, this.autoplay)
      } catch (e) {
        console.error('Autoplay start error:', e)
      }
    }
  }

  /**
   * Pause autoplay
   */
  pause() {
    this.isPaused = true
  }

  /**
   * Resume autoplay
   */
  resume() {
    this.isPaused = false
  }

  /**
   * Restart autoplay timer
   */
  restart() {
    try {
      clearInterval(this.timer)
      this.timer = null
      this.start()
    } catch (e) {
      console.error('Carousel restart error:', e)
    }
  }

  /**
   * Lazy load visible slides
   */
  lazyLoadVisible() {
    try {
      const loadImg = (slide) => {
        const img = slide?.querySelector('img')
        if (img && !img.getAttribute('src') && img.dataset.src) {
          img.src = img.dataset.src
        }
      }

      loadImg(this.slides[this.index])
      loadImg(this.slides[(this.index + 1) % this.count])
    } catch (e) {
      console.error('Lazy load error:', e)
    }
  }

  /**
   * Prefetch next slide image
   */
  prefetchNext() {
    try {
      const img = this.slides[(this.index + 1) % this.count]?.querySelector('img')
      if (img?.dataset?.src && !img.getAttribute('src')) {
        const preload = new Image()
        preload.src = img.dataset.src
      }
    } catch (e) {
      console.error('Prefetch error:', e)
    }
  }

  /**
   * Cleanup: remove all event listeners and timers
   */
  destroy() {
    try {
      clearInterval(this.timer)
      this.timer = null
      this.cleanups.forEach((cleanup) => cleanup())
      this.cleanups = []
    } catch (e) {
      console.error('Carousel destroy error:', e)
    }
  }
}
