export const validators = {
  required: v => v!==undefined && v!==null && String(v).trim()!=='',
  email: v => /\S+@\S+\.\S+/.test(v)
}
