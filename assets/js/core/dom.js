// DOM helpers
export const $ = (selector, ctx=document) => ctx.querySelector(selector)
export const $$ = (selector, ctx=document) => Array.from(ctx.querySelectorAll(selector))
export function createEl(tag, attrs={}){const el=document.createElement(tag);Object.entries(attrs).forEach(([k,v])=>el.setAttribute(k,v));return el}
