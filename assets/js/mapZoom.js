// mapZoom.js - simple toggle zoom on click
export function initMapZoom(){
  const frames = Array.from(document.querySelectorAll('[data-map-zoom]'))
  frames.forEach(frame=>{
    const img = frame.querySelector('img')
    if(!img) return
    frame.addEventListener('click', ()=>{
      frame.classList.toggle('is-zoomed')
      if(frame.classList.contains('is-zoomed')) img.style.transform = 'scale(1.6)'
      else img.style.transform = 'scale(1)'
    })
    frame.style.transition = 'transform 300ms ease'
    img.style.transition = 'transform 300ms ease'
  })
}
