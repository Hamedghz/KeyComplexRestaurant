// bottom navigation using IntersectionObserver
export default function bottomNav(root){
  const links = Array.from(root.querySelectorAll('a[data-target]'))
  const sections = links.map(l=>document.querySelector(l.dataset.target)).filter(Boolean)
  const obs = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{
      if(e.isIntersecting){
        const id = '#'+e.target.id
        links.forEach(l=> l.classList.toggle('active', l.dataset.target===id))
      }
    })
  },{threshold:0.6})
  sections.forEach(s=>obs.observe(s))
  return ()=>obs.disconnect()
}
