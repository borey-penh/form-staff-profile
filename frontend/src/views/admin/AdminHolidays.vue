<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { getHolidays, createHoliday, deleteHoliday } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const toast = useToastStore()
const loading = ref(true)
const year = ref(new Date().getFullYear())
const holidays = ref([])
const newName = ref('')
const newDate = ref('')
const saving = ref(false)

const YEARS = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - 1 + i)

const upcoming = computed(() => holidays.value)

async function load() {
  loading.value = true
  try {
    holidays.value = (await getHolidays(year.value)).data
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(year, load)

async function add() {
  if (!newName.value || !newDate.value) {
    return toast.show('Enter a name and pick a date.')
  }
  saving.value = true
  try {
    const res = await createHoliday(newName.value, newDate.value)
    toast.show('✓ ' + res.message)
    const addedYear = Number(newDate.value.slice(0, 4))
    newName.value = ''
    newDate.value = ''
    if (addedYear && addedYear !== year.value) year.value = addedYear
    else await load()
  } catch (e) {
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

async function remove(h) {
  if (!confirm(`Delete "${h.name}" on ${h.date}?`)) return
  try {
    await deleteHoliday(h.id)
    toast.show('Holiday deleted')
    await load()
  } catch (e) {
    toast.show(e.message)
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Holiday Calendar</h1>
        <div class="sub">Non-working days for the whole organization — used by timesheets and leave counting.</div>
      </div>
      <select v-model.number="year" style="width:auto">
        <option v-for="y in YEARS" :key="y" :value="y">{{ y }}</option>
      </select>
    </div>

    <section class="card">
      <div class="card-head">
        <h2>{{ year }} — {{ holidays.length }} holiday day(s)</h2>
      </div>

      <div class="help" style="margin-bottom:12px">
        Fixed-date holidays (New Year, King's Birthday, Independence Day…) are seeded automatically
        for every year. <strong>Movable holidays</strong> (Khmer New Year, Visak Bochea, Royal Plowing,
        Pchum Ben, Water Festival) change every year — add them here once announced.
      </div>

      <div class="upload-box" style="margin-bottom:16px">
        <div class="row3" style="flex:1; align-items:end; gap:12px; margin:0">
          <div class="field" style="margin:0">
            <label>Holiday Name</label>
            <input v-model="newName" placeholder="e.g. Water Festival Day 1">
          </div>
          <div class="field" style="margin:0">
            <label>Date</label>
            <input v-model="newDate" type="date">
          </div>
        </div>
        <button class="btn primary" :disabled="saving" @click="add">+ Add Holiday</button>
      </div>

      <LoadingState v-if="loading" />
      <EmptyState
        v-else-if="!upcoming.length"
        title="No holidays for this year"
        desc="Add the holidays announced for this year above."
      />
      <table v-else class="table">
        <thead><tr><th>Date</th><th>Weekday</th><th>Name</th><th></th></tr></thead>
        <tbody>
          <tr v-for="h in upcoming" :key="h.date + h.name">
            <td><strong>{{ h.date }}</strong></td>
            <td>{{ new Date(h.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' }) }}</td>
            <td>{{ h.name }}</td>
            <td>
              <button class="btn sm secondary" style="color:var(--red)" @click="remove(h)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
