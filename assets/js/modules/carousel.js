import { config } from '../core/config.js'

export default class Carousel {
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
    this.cleanups = []
    this.imageObserver = null

    if (this.root && this.track && this.count > 0) {
      this.init()
    }
  }

  init() {
    this.setupLayout()
    this.setupImages()
    this.bind()
    this.update()
    this.start()
  }

  setupLayout() {
    this.track.style.width = `${this.count * 100}%`
    this.slides.forEach((slide) => {
      slide.style.width = `${100 / this.count}%`
      slide.setAttribute('role', 'group')
    })
  }

  setupImages() {
    const loadImage = (img) => {
      if (!img || img.dataset.loaded === 'true') return
      const nextSrc = img.dataset.src
      if (!nextSrc) return
      img.src = nextSrc
      img.dataset.loaded = 'true'
    }

    this.loadImage = loadImage

    this.loadVisibleImages()

    if ('IntersectionObserver' in window) {
      this.imageObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target.querySelector('img')
            loadImage(img)
          }
        })
      }, { root: this.root, threshold: 0.15 })

      this.slides.forEach((slide) => this.imageObserver.observe(slide))
    } else {
      this.slides.forEach((slide) => loadImage(slide.querySelector('img')))
    }
  }

  bind() {
    this.root.setAttribute('role', 'region')
    this.root.setAttribute('aria-label', 'اسلایدر تصاویر')
    this.root.setAttribute('tabindex', '0')

    this.cleanups.push(this.on(this.nextBtn, 'click', () => this.goTo(this.index + 1)))
    this.cleanups.push(this.on(this.prevBtn, 'click', () => this.goTo(this.index - 1)))

    this.dots.forEach((dot) => {
      this.cleanups.push(this.on(dot, 'click', () => this.goTo(Number(dot.dataset.dot))))
    })

    this.cleanups.push(this.on(this.root, 'mouseenter', () => this.pause()))
    this.cleanups.push(this.on(this.root, 'focusin', () => this.pause()))
    this.cleanups.push(this.on(this.root, 'mouseleave', () => this.resume()))
    this.cleanups.push(this.on(this.root, 'focusout', () => this.resume()))

    this.cleanups.push(this.on(this.root, 'keydown', (e) => {
      const isRTL = document.documentElement.dir === 'rtl'
      if (e.key === 'ArrowRight') this.goTo(isRTL ? this.index - 1 : this.index + 1)
      if (e.key === 'ArrowLeft') this.goTo(isRTL ? this.index + 1 : this.index - 1)
    }))

    this.cleanups.push(this.on(this.root, 'touchstart', (e) => {
      this.touch.startX = e.touches?.[0]?.clientX ?? 0
    }, { passive: true }))

    this.cleanups.push(this.on(this.root, 'touchmove', (e) => {
      this.touch.deltaX = (e.touches?.[0]?.clientX ?? 0) - this.touch.startX
    }, { passive: true }))

    this.cleanups.push(this.on(this.root, 'touchend', () => {
      const threshold = config.carousel.touchThreshold
      if (this.touch.deltaX > threshold) this.goTo(this.index - 1)
      if (this.touch.deltaX < -threshold) this.goTo(this.index + 1)
      this.touch.deltaX = 0
    }))
  }

  on(el, event, handler, opts) {
    el?.addEventListener(event, handler, opts)
    return () => el?.removeEventListener(event, handler, opts)
  }

  update() {
    const x = -(this.index * 100) / this.count
    this.track.style.transform = `translate3d(${x}%, 0, 0)`

    this.slides.forEach((slide, i) => {
      slide.setAttribute('aria-hidden', String(i !== this.index))
    })

    this.dots.forEach((dot, i) => {
      const isActive = i === this.index
      dot.classList.toggle('is-active', isActive)
      dot.setAttribute('aria-current', isActive ? 'true' : 'false')
    })

    this.loadVisibleImages()
  }

  loadVisibleImages() {
    const current = this.slides[this.index]
    const next = this.slides[(this.index + 1) % this.count]
    const prev = this.slides[(this.index - 1 + this.count) % this.count]
    this.loadImage(current?.querySelector('img'))
    this.loadImage(next?.querySelector('img'))
    this.loadImage(prev?.querySelector('img'))
  }

  goTo(i) {
    this.index = (i + this.count) % this.count
    this.update()
    this.restart()
  }

  start() {
    if (!this.autoplay || this.timer) return
    this.timer = setInterval(() => {
      if (!this.isPaused) this.goTo(this.index + 1)
    }, this.autoplay)
  }

  pause() { this.isPaused = true }
  resume() { this.isPaused = false }

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
    this.imageObserver?.disconnect()
    this.imageObserver = null
  }
}
