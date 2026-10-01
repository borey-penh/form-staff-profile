export function calcAge(dobStr) {
  if (!dobStr) return ''
  const dob = new Date(dobStr)
  const now = new Date()
  const months =
    (now.getFullYear() - dob.getFullYear()) * 12 + (now.getMonth() - dob.getMonth())
  if (isNaN(months) || months < 0) return ''
  if (months < 12) return months + ' mos'
  return Math.floor(months / 12) + ' yrs'
}

export function todayISO() {
  return new Date().toISOString().slice(0, 10)
}

export function formatDate(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return isNaN(d) ? '—' : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
