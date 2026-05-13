import Carousel from './modules/carousel.js'
import bottomNav from './modules/bottomNav.js'

document.addEventListener('DOMContentLoaded', ()=>{
  const carEl = document.querySelector('[data-carousel]')
  if(carEl){
    const car = new Carousel(carEl, {autoplay:4000})
    // expose for debugging
    window.__carousel = car
  }

  const bn = document.querySelector('[data-bottom-nav]')
  if(bn){
    const stop = bottomNav(bn)
    window.__bottomNavStop = stop
  }
})
