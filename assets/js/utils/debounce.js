export function debounce(fn, wait=100){ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(this,args),wait) } }
