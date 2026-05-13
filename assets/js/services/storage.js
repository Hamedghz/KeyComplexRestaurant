// simple storage adapter with versioning and migration hook
export const storage = {
  key: 'webland:v1',
  get(){
    try{const raw = localStorage.getItem(this.key); return raw?JSON.parse(raw):{version:1,members:[]}}
    catch(e){return {version:1,members:[]}}
  },
  set(obj){ localStorage.setItem(this.key, JSON.stringify(obj)) },
  migrateIfNeeded(data){ if(!data.version){ data.version=1 } return data }
}
