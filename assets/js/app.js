import Carousel from './modules/carousel.js'
import bottomNav from './modules/bottomNav.js'
import initTabs from './modules/tabs.js'
import initClubForm from './modules/form.js'

document.addEventListener('DOMContentLoaded', () => {
  const header=document.querySelector('.header')
  window.addEventListener('scroll',()=>header?.classList.toggle('is-scrolled',window.scrollY>8),{passive:true})

  const carEl = document.querySelector('[data-carousel]')
  if (carEl) new Carousel(carEl, { autoplay: 5000 })

  const bn = document.querySelector('[data-bottom-nav]')
  if (bn) bottomNav(bn)

  const tabs=document.querySelector('[data-tabs]')
  if(tabs) initTabs(tabs)

  const form=document.querySelector('[data-club-form]')
  if(form) initClubForm(form)
})
