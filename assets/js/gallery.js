// gallery.js
export function initGallery(){
  const tracks = Array.from(document.querySelectorAll('[data-gallery-track]'))
  tracks.forEach(track=>{
    // basic lazy-loading already in markup; optional snapping behavior
    track.style.scrollSnapType = 'x mandatory'
    const items = Array.from(track.querySelectorAll('.gallery-item__image'))
    items.forEach(img=>{
      // ensure loading attributes
      if(!img.hasAttribute('loading')) img.setAttribute('loading','lazy')
      if(!img.hasAttribute('decoding')) img.setAttribute('decoding','async')
    })
  })
}
