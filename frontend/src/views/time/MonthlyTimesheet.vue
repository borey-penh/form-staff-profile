<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { getHolidays, getLeaveBalances } from '@/services/portalService'
import { useRequestEdit } from '@/composables/useRequestEdit'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'
import LoadingState from '@/components/LoadingState.vue'

const toast = useToastStore()
const auth = useAuthStore()
const router = useRouter()

const { editId, loadForEdit, save } = useRequestEdit('Timesheet')

const now = new Date()
const month = ref(now.getMonth() + 1)
const year = ref(now.getFullYear())
const submitting = ref(false)
const loading = ref(true)

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']
const LEAVE_TYPES = ['Annual', 'Sick Leave', 'Special', 'Compassionate', 'Time in Lieu', 'Paternity', 'Unpaid', 'Study']
// Keep values already saved on old timesheets selectable when editing them.
const leaveTypeOptions = computed(() => {
  const legacy = [...new Set(entries.value.filter((e) => e.leave && !LEAVE_TYPES.includes(e.leave)).map((e) => e.leave))]
  return [...LEAVE_TYPES, ...legacy]
})

const holidayMap = ref({})   // 'YYYY-MM-DD' → name
const balances = ref([])

const todayIso = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

/* ---------- Day state (flat array, 1..31) ---------- */
function daysInMonth(m, y) {
  const n = new Date(y, m, 0).getDate()
  return Array.from({ length: n }, (_, i) => {
    const d = new Date(y, m - 1, i + 1)
    const iso = `${y}-${String(m).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`
    return {
      day: d.toLocaleDateString('en-US', { weekday: 'short' }),
      date: `${String(i + 1).padStart(2, '0')}/${String(m).padStart(2, '0')}/${y}`,
      iso,
      dayNum: i + 1,
      weekend: [0, 6].includes(d.getDay()),
      hours: '', leave: '',
    }
  })
}

const entries = ref(daysInMonth(month.value, year.value))

const holidayOf = (e) => holidayMap.value[e.iso]
const isNonWorking = (e) => e.weekend || !!holidayOf(e)

async function load() {
  loading.value = true
  try {
    const [h, b] = await Promise.all([
      getHolidays(year.value),
      getLeaveBalances().catch(() => ({ data: [] })),
    ])
    holidayMap.value = Object.fromEntries(h.data.map((x) => [x.date, x.name]))
    balances.value = b.data ?? []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const f = await loadForEdit()
  if (f?.month) {
    storedEntries.value = f.entries ?? []
    month.value = Number(f.month)
    year.value = Number(f.year)
  }
  await load()
  rebuild()
})
watch([month, year], async () => { await load(); rebuild() })

function rebuild() {
  entries.value = daysInMonth(month.value, year.value)
  // While editing a submitted timesheet, re-apply the saved values on every
  // rebuild (month switches regenerate the grid from scratch).
  if (editId.value && storedEntries.value) applyStored(storedEntries.value)
}

/* Saved timesheet entries, matched to the grid by ISO date. */
const storedEntries = ref(null)

function applyStored(list) {
  const byDate = Object.fromEntries((list ?? []).map((e) => [e.date, e]))
  for (const e of entries.value) {
    const s = byDate[e.iso]
    if (!s) continue
    e.hours = s.hours ? String(s.hours) : ''
    e.leave = s.leave ?? ''
  }
}

function colClass(e) {
  return {
    'c-weekend': e.weekend && !holidayOf(e),
    'c-holiday': !!holidayOf(e),
    'c-leave': !!e.leave,
    'c-today': e.iso === todayIso,
  }
}

/* ---------- Totals ---------- */
const totalHours = computed(() =>
  Math.round(entries.value.reduce((s, e) => s + (e.leave ? 0 : (parseFloat(e.hours) || 0)), 0) * 100) / 100)

const overWorked = computed(() =>
  entries.value.filter((e) => !e.leave && !isNonWorking(e) && (parseFloat(e.hours) || 0) > 10))

const leaveCounts = computed(() => {
  const map = {}
  for (const e of entries.value) {
    if (e.leave) map[e.leave] = (map[e.leave] ?? 0) + 1
  }
  return map
})

const totalLeaveDays = computed(() => Object.values(leaveCounts.value).reduce((s, n) => s + n, 0))

function balanceFor(type) {
  return balances.value.find((x) => x.type === type) ?? null
}

const startDate = computed(() => `01-${String(month.value).padStart(2, '0')}-${year.value}`)
const endDate = computed(() => `${String(new Date(year.value, month.value, 0).getDate()).padStart(2, '0')}-${String(month.value).padStart(2, '0')}-${year.value}`)

/* ---------- Submit ---------- */
async function submit() {
  submitting.value = true
  try {
    await save({
      month: month.value,
      year: year.value,
      entries: entries.value.map(({ day, iso, weekend, hours, leave }) => ({
        day, date: iso, weekend,
        holiday: holidayMap.value[iso] ?? null,
        hours: leave || weekend || holidayMap.value[iso] ? 0 : (parseFloat(hours) || 0),
        leave: leave || null,
      })),
      totalHours: totalHours.value,
      totalLeaveDays: totalLeaveDays.value,
    }, null)
    toast.show(editId.value ? '✓ Timesheet updated' : '✓ Timesheet submitted for approval')
    router.push('/requests')
  } catch (e) {
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Monthly Timesheet</h1>
        <div class="sub">Enter your net working hours per day (already excluding the 12:00–13:30 unpaid break).</div>
      </div>
      <div style="display:flex; gap:10px">
        <select v-model.number="month" style="width:auto">
          <option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option>
        </select>
        <input v-model.number="year" type="number" style="width:100px">
      </div>
    </div>

    <div v-if="editId" class="help" style="margin:-4px 0 12px">✏️ Editing timesheet request #{{ editId }} — it stays editable until someone approves or rejects it.</div>

    <!-- Employee header (like the paper form) -->
    <section class="card">
      <div class="ts-header">
        <div class="ts-cell"><span>Employee ID</span><strong>{{ auth.user?.staffId ?? '—' }}</strong></div>
        <div class="ts-cell"><span>Full Name</span><strong>{{ auth.fullName || '—' }}</strong></div>
        <div class="ts-cell"><span>Position</span><strong>{{ auth.user?.position ?? '—' }}</strong></div>
        <div class="ts-cell"><span>Duty Station</span><strong>{{ auth.user?.department ?? '—' }}</strong></div>
        <div class="ts-cell"><span>Month</span><strong>{{ MONTHS[month - 1] }} {{ year }}</strong></div>
        <div class="ts-cell"><span>Period</span><strong>{{ startDate }} → {{ endDate }}</strong></div>
      </div>

      <div class="help ts-legend" style="margin-bottom:14px">
        <span class="badge neutral">Weekend</span>
        <span class="badge pending">Public holiday</span>
        <span class="badge review">Leave day</span>
        <span>Non-working days need no hours. Working on a holiday/weekend requires an Overtime request.</span>
      </div>

      <LoadingState v-if="loading" />

      <!-- Excel-style grid: dates across the top, hours + leave rows -->
      <div v-else class="ts-scroll">
        <table class="ts-xl">
          <thead>
            <tr>
              <th class="row-label">
                <span class="rl-big">{{ MONTHS[month - 1] }}</span>
                <span class="rl-small">{{ year }}</span>
              </th>
              <th v-for="e in entries" :key="'h' + e.iso" :class="colClass(e)">
                <span class="dow">{{ e.day }}</span>
                <strong class="dnum">{{ e.dayNum }}</strong>
                <span v-if="holidayOf(e)" class="hol" :title="holidayOf(e)">🎉</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="row-label"><span class="rl-tag hours">Hours</span></td>
              <td v-for="e in entries" :key="'hrs' + e.iso" :class="colClass(e)">
                <input
                  v-model="e.hours"
                  type="number"
                  min="0"
                  max="16"
                  step="0.25"
                  :placeholder="isNonWorking(e) || !!e.leave ? '' : '0'"
                  :disabled="isNonWorking(e) || !!e.leave"
                  :title="`Hours for ${e.date}`"
                >
              </td>
            </tr>
            <tr>
              <td class="row-label"><span class="rl-tag leave">Leave</span></td>
              <td v-for="e in entries" :key="'lv' + e.iso" :class="colClass(e)">
                <select v-model="e.leave" :disabled="isNonWorking(e)" :title="`Leave for ${e.date}`">
                  <option value="">–</option>
                  <option v-for="t in leaveTypeOptions" :key="t" :value="t">{{ t }}</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="ts-summary">
        <div class="total-bar">
          <span>Total Working Hours <span class="help">({{ totalLeaveDays }} leave day(s) marked)</span></span>
          <strong>{{ totalHours.toFixed(2) }} h</strong>
        </div>
        <div v-if="overWorked.length" class="help" style="color:var(--red)">
          ⚠ More than 10 h on: {{ overWorked.map(e => e.date).join(', ') }} — hours above 10 need an Overtime request.
        </div>
      </div>
    </section>

    <!-- Leaves record (like the paper form) -->
    <section class="card" style="margin-top:18px">
      <div class="card-head"><h2>Leaves Record — {{ MONTHS[month - 1] }} {{ year }}</h2></div>
      <table class="table">
        <thead><tr><th>Type of Leave</th><th>Allowance</th><th>Used (this month)</th><th>Balance</th></tr></thead>
        <tbody>
          <tr v-for="t in LEAVE_TYPES" :key="t">
            <td>{{ t }}</td>
            <td>{{ balanceFor(t)?.entitled ?? '—' }}</td>
            <td>{{ leaveCounts[t] ?? 0 }}</td>
            <td>{{ balanceFor(t) ? balanceFor(t).remaining : '—' }}</td>
          </tr>
        </tbody>
      </table>

      <div class="actions" style="margin-top:16px">
        <span class="help" style="margin-right:auto">Submitted by: <strong>{{ auth.fullName }}</strong> — approval follows the normal workflow.</span>
        <button class="btn secondary" @click="$router.back()">Cancel</button>
        <button class="btn primary" :disabled="submitting" @click="submit">
          <span v-if="submitting">Saving…</span>
          <span v-else>{{ editId ? '✓ Update Timesheet' : '✓ Submit Timesheet' }}</span>
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Employee header strip — paper-form style */
.ts-header {
  display: grid;
  grid-template-columns: repeat(3, minmax(170px, 1fr));
  gap: 12px 28px;
  padding: 15px 18px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: linear-gradient(180deg, #fafcfc, #fff);
  margin-bottom: 14px;
}
.ts-cell { display: flex; flex-direction: column; gap: 2px; font-size: 13.5px; }
.ts-cell span { color: var(--muted); font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; }
.ts-cell strong { font-size: 14px; }

.ts-legend { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* Excel-style grid */
.ts-scroll { overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }
.ts-xl { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; }

.ts-xl th, .ts-xl td { border-bottom: 1px solid #eef2f7; border-right: 1px solid #eef2f7; }
.ts-xl th:last-child, .ts-xl td:last-child { border-right: none; }

.ts-xl thead th {
  padding: 8px 6px; text-align: center; vertical-align: top;
  min-width: 64px; background: #fff;
}
.ts-xl .dow { display: block; font-size: 9.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
.ts-xl .dnum { display: block; font-size: 14.5px; line-height: 1.2; }
.ts-xl .hol { display: block; font-size: 10px; line-height: 1.3; }

/* Row labels (sticky first column) */
.ts-xl .row-label {
  position: sticky; left: 0; z-index: 2; min-width: 92px; width: 92px;
  background: #fff; text-align: left; padding: 8px 10px; vertical-align: middle;
  box-shadow: 4px 0 6px -4px rgba(15,23,42,.12);
}
.rl-big { display: block; font-size: 13.5px; font-weight: 800; }
.rl-small { display: block; font-size: 11px; color: var(--muted); }
.rl-tag { display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 3px 8px; border-radius: 6px; }
.rl-tag.hours { background: var(--primary-soft); color: var(--primary); }
.rl-tag.leave { background: var(--blue-soft, #e0f2fe); color: var(--blue, #2563eb); }

/* Input rows — borderless fields so cells don't look like nested boxes */
.ts-xl tbody td { padding: 4px 5px; text-align: center; background: #fff; }
.ts-xl :is(input, select) {
  width: 100%; padding: 6px 4px; font-size: 12px; border-radius: 7px; text-align: center;
  background: transparent; border: 1px solid transparent;
}
.ts-xl :is(input:not(:disabled):hover, select:not(:disabled):hover) { background: #fff; border-color: var(--border); }
.ts-xl :is(input:disabled, select:disabled) { background: transparent; color: var(--muted); opacity: 1; }
.ts-xl input { font-variant-numeric: tabular-nums; }
.ts-xl input[type='number']::-webkit-outer-spin-button,
.ts-xl input[type='number']::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.ts-xl input[type='number'] { -moz-appearance: textfield; appearance: textfield; }
.ts-xl select { padding: 5px 2px; font-size: 10.5px; }

/* Column states */
.c-weekend { background: var(--field-bg) !important; }
.c-weekend .dow, .c-weekend .dnum { color: var(--muted); }
.c-holiday { background: var(--amber-soft, #fef3c7) !important; }
.c-holiday .dow, .c-holiday .dnum { color: var(--amber, #b45309); }
.c-leave { background: var(--blue-soft, #e0f2fe) !important; }

/* Today — one continuous outline down the column, not a ring on every cell */
.c-today { background: var(--primary-soft) !important; }
.c-today .dow { color: var(--primary); }
.c-today .dnum { color: var(--primary); font-weight: 800; }
thead th.c-today { box-shadow: inset 2px 0 0 var(--primary), inset -2px 0 0 var(--primary), inset 0 2px 0 var(--primary); }
tbody td.c-today { box-shadow: inset 2px 0 0 var(--primary), inset -2px 0 0 var(--primary); }
tbody tr:last-child td.c-today { box-shadow: inset 2px 0 0 var(--primary), inset -2px 0 0 var(--primary), inset 0 -2px 0 var(--primary); }

/* Summary */
.ts-summary { margin-top: 14px; }
.ts-summary .total-bar { margin-bottom: 8px; }

@media (max-width: 720px) {
  .ts-header { grid-template-columns: 1fr 1fr; }
  .ts-legend { align-items: flex-start; flex-direction: column; }
}
</style>
