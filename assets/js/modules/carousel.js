export default class Carousel{
  constructor(root, opts={}){
    this.root = root
    this.track = root.querySelector('.carousel-track')
    this.slides = Array.from(root.querySelectorAll('.carousel-slide'))
    this.prevBtn = root.querySelector('.carousel-prev')
    this.nextBtn = root.querySelector('.carousel-next')
    this.dots = Array.from(root.querySelectorAll('[data-dot]'))
    this.index = 0
    this.count = this.slides.length
    this.autoplay = opts.autoplay || 4000
    this.timer = null
    this.isPaused = false
    this.touch = {startX:0,deltaX:0}
    this.init()
  }
  init(){
    if(!this.track || this.count===0) return
    this.update()
    this.bind()
    this.start()
    this.lazyLoadVisible()
  }
  bind(){
    this.nextBtn?.addEventListener('click', ()=>this.goTo(this.index+1))
    this.prevBtn?.addEventListener('click', ()=>this.goTo(this.index-1))
    this.dots.forEach(dot=>dot.addEventListener('click', ()=>this.goTo(Number(dot.dataset.dot))))

    this.root.addEventListener('mouseenter', ()=>this.pause())
    this.root.addEventListener('focusin', ()=>this.pause())
    this.root.addEventListener('mouseleave', ()=>this.resume())
    this.root.addEventListener('focusout', ()=>this.resume())
    this.root.addEventListener('keydown', (e)=>{
      if(e.key === 'ArrowRight') this.goTo(this.index+1)
      if(e.key === 'ArrowLeft') this.goTo(this.index-1)
    })
    this.root.addEventListener('touchstart', e=>{this.touch.startX = e.touches[0].clientX}, {passive:true})
    this.root.addEventListener('touchmove', e=>{this.touch.deltaX = e.touches[0].clientX - this.touch.startX}, {passive:true})
    this.root.addEventListener('touchend', ()=>{ if(this.touch.deltaX>40) this.goTo(this.index-1); else if(this.touch.deltaX<-40) this.goTo(this.index+1); this.touch.deltaX=0 })
    this.root.setAttribute('role','region')
    this.root.setAttribute('tabindex','0')
    window.addEventListener('resize', ()=>this.update())
  }
  update(){
    const x = -this.index * 100
    this.track.style.transform = `translate3d(${x}%,0,0)`
    this.slides.forEach((s,i)=> s.setAttribute('aria-hidden', i!==this.index))
    this.dots.forEach((dot,i)=>dot.classList.toggle('is-active',i===this.index))
  }
  goTo(i){ this.index = (i + this.count) % this.count; this.update(); this.lazyLoadVisible(); this.prefetchNext(); this.restart() }
  start(){ if(this.autoplay && !this.timer){ this.timer = setInterval(()=>{ if(!this.isPaused) this.goTo(this.index+1) }, this.autoplay) } }
  pause(){ this.isPaused = true }
  resume(){ this.isPaused = false }
  restart(){ clearInterval(this.timer); this.timer=null; this.start() }
  lazyLoadVisible(){
    const loadImg = (slide)=>{ const img = slide?.querySelector('img[data-src]'); if(img && !img.getAttribute('src')){ img.src = img.dataset.src } }
    loadImg(this.slides[this.index]); loadImg(this.slides[(this.index+1)%this.count])
  }
  prefetchNext(){ const img = this.slides[(this.index+1)%this.count]?.querySelector('img[data-src]'); if(img && !img.getAttribute('src')){ const p=new Image(); p.src = img.dataset.src } }
}
