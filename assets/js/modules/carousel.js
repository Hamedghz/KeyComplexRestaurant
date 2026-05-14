import { config } from '../core/config.js'

export default class Carousel {
  constructor(root, opts = {}) {
    this.root = root
    this.track = root?.querySelector('[data-carousel-track]')
    this.slides = Array.from(root?.querySelectorAll('[data-slide]') ?? [])
    this.prevBtn = root?.querySelector('[data-carousel-prev]')
    this.nextBtn = root?.querySelector('[data-carousel-next]')
    this.dots = Array.from(root?.querySelectorAll('[data-dot]') ?? [])

    this.index = 0
    this.count = this.slides.length
    this.autoplay = opts.autoplay ?? config.carousel.autoplay
    this.timer = null
    this.isPaused = false
    this.touchStartX = 0
    this.touchDeltaX = 0
    this.cleanups = []

    if (this.root && this.track && this.count > 0) this.init()
  }

  init() {
    this.root.setAttribute('role', 'region')
    this.root.setAttribute('aria-label', 'اسلایدر اصلی')
    this.root.setAttribute('tabindex', '0')

    this.slides.forEach((slide, i) => {
      slide.setAttribute('role', 'group')
      slide.setAttribute('aria-label', `اسلاید ${i + 1} از ${this.count}`)
    })

    this.bind()
    this.update()
    this.start()
  }

  bind() {
    this.cleanups.push(this.on(this.nextBtn, 'click', () => this.goTo(this.index + 1)))
    this.cleanups.push(this.on(this.prevBtn, 'click', () => this.goTo(this.index - 1)))

    this.dots.forEach((dot) => {
      this.cleanups.push(this.on(dot, 'click', () => this.goTo(Number(dot.dataset.dot))))
    })

    this.cleanups.push(this.on(this.root, 'mouseenter', () => this.pause()))
    this.cleanups.push(this.on(this.root, 'mouseleave', () => this.resume()))
    this.cleanups.push(this.on(this.root, 'focusin', () => this.pause()))
    this.cleanups.push(this.on(this.root, 'focusout', () => this.resume()))

    this.cleanups.push(this.on(this.root, 'keydown', (event) => {
      if (event.key === 'ArrowRight') this.goTo(this.index - 1)
      if (event.key === 'ArrowLeft') this.goTo(this.index + 1)
    }))

    this.cleanups.push(this.on(this.root, 'touchstart', (event) => {
      this.touchStartX = event.touches?.[0]?.clientX ?? 0
    }, { passive: true }))

    this.cleanups.push(this.on(this.root, 'touchmove', (event) => {
      this.touchDeltaX = (event.touches?.[0]?.clientX ?? 0) - this.touchStartX
    }, { passive: true }))

    this.cleanups.push(this.on(this.root, 'touchend', () => {
      const threshold = config.carousel.touchThreshold
      if (this.touchDeltaX > threshold) this.goTo(this.index - 1)
      if (this.touchDeltaX < -threshold) this.goTo(this.index + 1)
      this.touchDeltaX = 0
    }))
  }

  on(el, event, handler, options) {
    el?.addEventListener(event, handler, options)
    return () => el?.removeEventListener(event, handler, options)
  }

  update() {
    this.track.style.transform = `translate3d(${this.index * -100}%, 0, 0)`

    this.slides.forEach((slide, i) => {
      slide.setAttribute('aria-hidden', String(i !== this.index))
    })

    this.dots.forEach((dot, i) => {
      const isActive = i === this.index
      dot.classList.toggle('is-active', isActive)
      dot.setAttribute('aria-current', isActive ? 'true' : 'false')
    })
  }

  goTo(nextIndex) {
    this.index = (nextIndex + this.count) % this.count
    this.update()
    this.restart()
  }

  start() {
    if (!this.autoplay || this.timer) return

    this.timer = setInterval(() => {
      if (!this.isPaused) this.goTo(this.index + 1)
    }, this.autoplay)
  }

  pause() {
    this.isPaused = true
  }

  resume() {
    this.isPaused = false
  }

  restart() {
    clearInterval(this.timer)
    this.timer = null
    this.start()
  }

  destroy() {
    clearInterval(this.timer)
    this.timer = null
    this.cleanups.forEach((cleanup) => cleanup())
    this.cleanups = []
  }
}
