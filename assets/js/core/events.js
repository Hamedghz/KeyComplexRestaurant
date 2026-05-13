export function on(el, event, handler, opts){el.addEventListener(event, handler, opts);return ()=>el.removeEventListener(event,handler,opts)}
export function delegate(root, selector, event, handler){root.addEventListener(event, e=>{const t=e.target.closest(selector); if(t) handler(e,t)})}
