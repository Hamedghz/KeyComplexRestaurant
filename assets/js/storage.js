// storage.js - club members adapter with versioning
const CLUB_KEY = 'restaurantKey.clubMembers:v1'
export function readStore(){
  try{const raw = localStorage.getItem(CLUB_KEY); if(!raw) return {version:1,members:[]}; const parsed = JSON.parse(raw); if(!parsed.version) parsed.version=1; return parsed}catch(e){return {version:1,members:[]}}
}
export function writeStore(obj){ localStorage.setItem(CLUB_KEY, JSON.stringify(obj)) }
export function addMember(phone){ const s=readStore(); s.members.push({phone,joined:new Date().toISOString()}); writeStore(s); return s }
export function listMembers(){ return readStore().members }
