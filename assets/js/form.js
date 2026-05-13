// form.js - club form handling + validation
import { addMember } from './storage.js'
export function initClubForm(){
  const form = document.getElementById('clubForm')
  if(!form) return
  const phone = form.querySelector('#phone')
  const msg = document.getElementById('formMessage')
  form.addEventListener('submit', (e)=>{
    e.preventDefault()
    const val = phone.value.trim()
    const ok = /^0?9\d{9}$/.test(val) || /^\+?98\d{10}$/.test(val)
    if(!ok){ msg.textContent = 'شماره موبایل نامعتبر است'; msg.classList.add('error'); msg.setAttribute('aria-live','polite'); return }
    addMember(val)
    msg.textContent = 'با موفقیت ثبت شدید؛ متشکریم.'
    msg.classList.remove('error'); msg.classList.add('success')
    phone.value = ''
  })
}
