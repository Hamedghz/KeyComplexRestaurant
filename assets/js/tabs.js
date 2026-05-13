// tabs.js
export function initMenuTabs(){
  const containers = Array.from(document.querySelectorAll('[data-tabs]'))
  containers.forEach(container=>{
    const tabs = Array.from(container.querySelectorAll('.menu-tab'))
    const panelsRoot = container.closest('.section') || document
    const panels = Array.from(document.querySelectorAll('.menu-panel'))
    tabs.forEach(tab=>{
      tab.addEventListener('click', ()=>{
        const target = tab.dataset.tabTarget
        tabs.forEach(t=>{t.classList.toggle('is-active', t===tab); t.setAttribute('aria-selected', t===tab)})
        panels.forEach(p=> p.classList.toggle('is-active', p.dataset.tabPanel===target))
      })
      tab.addEventListener('keydown', (e)=>{
        if(e.key==='ArrowRight' || e.key==='ArrowLeft'){
          const idx = tabs.indexOf(tab)
          const next = e.key==='ArrowRight' ? tabs[(idx+1)%tabs.length] : tabs[(idx-1+tabs.length)%tabs.length]
          next.focus(); next.click()
        }
      })
    })
  })
}
