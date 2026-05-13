export function throttle(fn, limit=100){ let last=0; return (...args)=>{ const now=Date.now(); if(now-last>=limit){ last=now; fn.apply(this,args) } } }
