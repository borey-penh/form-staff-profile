// Compliance presentation helpers: derive category, icon and color from the API fields
// (id, title, description, policyUrl, signed) without changing the backend.

export const CATEGORIES = [
  {
    key: 'Safeguarding',
    match: /safeguard|pseah/i,
    icon: '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="m9 12 2 2 4-4"/>',
    color: '#0e6e66',
    soft: '#e6f4f2',
  },
  {
    key: 'Ethics & Conduct',
    match: /conduct|conflict|interest|ethic/i,
    icon: '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    color: '#7c3aed',
    soft: '#f1ebfd',
  },
  {
    key: 'Financial & Integrity',
    match: /corrupt|fraud|money|launder|financial|anti-/i,
    icon: '<circle cx="12" cy="12" r="9"/><path d="M12 7v10"/><path d="M9.5 9.5c0-1 1.1-1.8 2.5-1.8s2.5.8 2.5 1.8-1.1 1.6-2.5 2-2.5 1-2.5 2 1.1 1.8 2.5 1.8 2.5-.8 2.5-1.8"/>',
    color: '#b45309',
    soft: '#fdf1df',
  },
  {
    key: 'Other Policies',
    match: /.*/,
    icon: '<path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7z"/><path d="M14 3v4h4"/><path d="M9 13h6M9 17h6"/>',
    color: '#2563eb',
    soft: '#e8effd',
  },
]

export function categoryOf(title = '') {
  return CATEGORIES.find((c) => c.match.test(title)) ?? CATEGORIES[CATEGORIES.length - 1]
}

export function statusOf(item) {
  // API gives signed true/false. Expired is reserved for future due-date support.
  return item.signed ? 'Completed' : 'Pending'
}

export function summarize(items = []) {
  const total = items.length
  const completed = items.filter((i) => i.signed).length
  return {
    total,
    completed,
    pending: total - completed,
    expired: 0,
    progress: total ? Math.round((completed / total) * 100) : 0,
  }
}

export function historyOf(items = []) {
  return items
    .filter((i) => i.signed)
    .map((i) => ({ title: i.title, status: 'Completed' }))
    .sort((a, b) => a.title.localeCompare(b.title))
}
