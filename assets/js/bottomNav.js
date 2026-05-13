// bottomNav.js - IO with throttled scroll fallback
export function initBottomNav(){
  const nav = document.querySelector('[data-bottom-nav]')
  if(!nav) return
  const links = Array.from(nav.querySelectorAll('[data-nav-section]'))
  const sections = links.map(l=>document.getElementById(l.dataset.navSection)).filter(Boolean)
  let observer
  if('IntersectionObserver' in window){
    observer = new IntersectionObserver((entries)=>{
      entries.forEach(e=>{
        if(e.isIntersecting){
          const id = e.target.id
          links.forEach(a=> a.classList.toggle('is-active', a.dataset.navSection===id))
        }
      })
    },{threshold:0.6})
    sections.forEach(s=>observer.observe(s))
  } else {
    // fallback
    let lastActive = ''
    const onScroll = ()=>{
      let found = null
      const topOffset = window.pageYOffset + (parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-height'))||72) + 10
      for(const s of sections){ if(s.offsetTop <= topOffset) found = s }
      const id = found?found.id:''
      if(id !== lastActive){ lastActive = id; links.forEach(a=> a.classList.toggle('is-active', a.dataset.navSection===id)) }
    }
    let t
    window.addEventListener('scroll', ()=>{ clearTimeout(t); t=setTimeout(onScroll, 120) }, {passive:true})
    onScroll()
  }
}
