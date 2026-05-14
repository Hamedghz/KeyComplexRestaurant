export default function initTabs(root){
  const tabs=[...root.querySelectorAll('[role="tab"]')]
  const panels=[...document.querySelectorAll('[data-panel]')]
  const activate=(name)=>{tabs.forEach(t=>t.setAttribute('aria-selected',String(t.dataset.tab===name)));panels.forEach(p=>p.classList.toggle('is-active',p.dataset.panel===name))}
  tabs.forEach(t=>t.addEventListener('click',()=>activate(t.dataset.tab)))
}
