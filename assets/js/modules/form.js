import { storage } from '../services/storage.js'

const IR_MOBILE=/^09\d{9}$/
export default function initClubForm(form){
  const message=form.querySelector('[data-form-message]')
  form.addEventListener('submit',(e)=>{
    e.preventDefault()
    const phone=form.phone.value.trim()
    if(!IR_MOBILE.test(phone)){message.textContent='شماره موبایل معتبر نیست';return}
    const data=storage.get();data.members.push({phone,at:Date.now()});storage.set(data)
    message.textContent='عضویت شما ثبت شد'
    form.reset()
  })
}
