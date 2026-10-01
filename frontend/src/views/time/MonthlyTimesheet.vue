<script setup>
import { computed, ref } from 'vue'
import { submitRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const now = new Date()
const month = ref(now.getMonth() + 1)
const year = ref(now.getFullYear())
const submitting = ref(false)

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']

function daysInMonth(m, y) {
  const n = new Date(y, m, 0).getDate()
  return Array.from({ length: n }, (_, i) => {
    const d = new Date(y, m - 1, i + 1)
    return {
      day: d.toLocaleDateString('en-US', { weekday: 'short' }),
      date: `${String(i + 1).padStart(2, '0')}/${String(m).padStart(2, '0')}/${y}`,
      iso: `${y}-${String(m).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`,
      weekend: [0, 6].includes(d.getDay()),
      timeIn: '', timeOut: '',
    }
  })
}

const entries = ref(daysInMonth(month.value, year.value))

function rebuild() {
  entries.value = daysInMonth(month.value, year.value)
}

function hoursOf(e) {
  if (!e.timeIn || !e.timeOut) return null
  const [sh, sm] = e.timeIn.split(':').map(Number)
  const [eh, em] = e.timeOut.split(':').map(Number)
  let mins = (eh * 60 + em) - (sh * 60 + sm)
  if (mins < 0) return null
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m ? `${h}h${String(m).padStart(2, '0')}` : `${h}`
}

function hoursNum(e) {
  if (!e.timeIn || !e.timeOut) return 0
  const [sh, sm] = e.timeIn.split(':').map(Number)
  const [eh, em] = e.timeOut.split(':').map(Number)
  let mins = (eh * 60 + em) - (sh * 60 + sm)
  if (mins < 0) mins = 0
  return Math.round((mins / 60) * 100) / 100
}

const totalHours = computed(() => entries.value.reduce((s, e) => s + hoursNum(e), 0))

async function submit() {
  submitting.value = true
  try {
    await submitRequest('Timesheet', {
      month: month.value,
      year: year.value,
      entries: entries.value.map(({ day, iso, weekend, timeIn, timeOut }) => ({
        day, date: iso, weekend, timeIn, timeOut, hours: hoursNum({ timeIn, timeOut }),
      })),
      totalHours: Math.round(totalHours.value * 100) / 100,
    })
    toast.show('✓ Timesheet submitted for approval')
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
        <div class="sub">Enter your daily working hours and submit for approval.</div>
      </div>
      <div style="display:flex; gap:10px">
        <select v-model.number="month" @change="rebuild" style="width:auto">
          <option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option>
        </select>
        <input v-model.number="year" type="number" style="width:100px" @change="rebuild">
      </div>
    </div>

    <section class="card">
      <div class="card-head"><h2>{{ MONTHS[month - 1] }} {{ year }}</h2></div>
      <div style="overflow-x:auto">
        <table class="table">
          <thead><tr><th>Day</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th></tr></thead>
          <tbody>
            <tr v-for="e in entries" :key="e.iso" :style="e.weekend ? 'background:var(--field-bg)' : ''">
              <td>{{ e.day }}</td>
              <td>{{ e.date }}</td>
              <td style="width:130px"><input v-model="e.timeIn" type="time"></td>
              <td style="width:130px"><input v-model="e.timeOut" type="time"></td>
              <td>{{ hoursOf(e) ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px">
        <strong>Total Working Hours: {{ Math.round(totalHours * 100) / 100 }}</strong>
        <button class="btn primary" :disabled="submitting" @click="submit">Submit Timesheet</button>
      </div>
    </section>
  </div>
</template>
