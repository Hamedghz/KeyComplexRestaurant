// app.js - entry
import { initSmoothScroll } from './smoothScroll.js'
import { initMenuTabs } from './tabs.js'
import { initGallery } from './gallery.js'
import { initClubForm } from './form.js'
import { initMapZoom } from './mapZoom.js'
import { initBottomNav } from './bottomNav.js'
import Carousel from './modules/carousel.js'

document.addEventListener('DOMContentLoaded', ()=>{
  initSmoothScroll()
  initMenuTabs()
  initGallery()
  initClubForm()
  initMapZoom()
  initBottomNav()

  const carEl = document.querySelector('[data-carousel]')
  if(carEl){
    const car = new Carousel(carEl, {autoplay:4000})
    window.__carousel = car
  }
})
