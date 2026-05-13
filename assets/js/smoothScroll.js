// smoothScroll.js
export function initSmoothScroll(){
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const links = Array.from(document.querySelectorAll('a[href^="#"]'))
  links.forEach(a=>{
    a.addEventListener('click', (e)=>{
      const href = a.getAttribute('href')
      if(!href || href === '#') return
      const target = document.querySelector(href)
      if(!target) return
      e.preventDefault()
      const root = document.documentElement
      const headerHeight = parseInt(getComputedStyle(root).getPropertyValue('--header-height')) || 72
      const offset = headerHeight
      const top = target.getBoundingClientRect().top + window.pageYOffset - offset
      if(prefersReduced){ window.scrollTo(0, top); return }
      window.scrollTo({top,behavior:'smooth'})
    })
  })
}
