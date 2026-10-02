<script setup>
import { computed, onMounted, ref } from 'vue'
import { getVehicles } from '@/services/portalService'
import { useRequestEdit } from '@/composables/useRequestEdit'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const { editId, loadForEdit, save } = useRequestEdit('Fuel')

const vehicles = ref([])
const vehicleId = ref('')
const now = new Date()
const month = ref(now.getMonth() + 1)
const year = ref(now.getFullYear())
const records = ref([{ date: '', mileage: '', liters: '', cost: '' }])
const submitting = ref(false)

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']

onMounted(async () => {
  vehicles.value = (await getVehicles()).data
  if (vehicles.value[0]) vehicleId.value = vehicles.value[0].id
  await loadForEdit((f) => {
    if (f.vehicleId) vehicleId.value = f.vehicleId
    if (f.month) month.value = Number(f.month)
    if (f.year) year.value = Number(f.year)
    if (Array.isArray(f.records) && f.records.length) {
      records.value = f.records.map((r) => ({
        date: r.date ?? '', mileage: r.mileage ?? '', liters: r.liters ?? '', cost: r.cost ?? '',
      }))
    }
  })
})

const totalLiters = computed(() => records.value.reduce((s, r) => s + (parseFloat(r.liters) || 0), 0))
const totalCost = computed(() => records.value.reduce((s, r) => s + (parseFloat(r.cost) || 0), 0))

function addRecord() {
  records.value.push({ date: '', mileage: '', liters: '', cost: '' })
}

async function submit() {
  const valid = records.value.filter((r) => r.date && r.liters)
  if (!vehicleId.value || valid.length === 0) {
    return toast.show('Select a vehicle and add at least one record with date + liters.')
  }
  submitting.value = true
  try {
    await save({
      vehicleId: Number(vehicleId.value),
      month: month.value,
      year: year.value,
      records: valid.map((r) => ({
        date: r.date,
        mileage: parseFloat(r.mileage) || 0,
        liters: parseFloat(r.liters) || 0,
        cost: parseFloat(r.cost) || 0,
      })),
    }, null)
    toast.show(editId.value ? '✓ Fuel log-sheet updated' : '✓ Fuel log-sheet submitted')
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
        <h1>Fuel Log-Sheet</h1>
        <div class="sub">Record fuel usage per vehicle for a month and submit.</div>
      </div>
    </div>

    <section class="card">
      <div v-if="editId" class="help" style="margin-bottom:12px">✏️ Editing request #{{ editId }} — it stays editable until someone approves or rejects it.</div>
      <div class="row2">
        <div class="field">
          <label>Vehicle <span class="req">*</span></label>
          <select v-model="vehicleId">
            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
          </select>
        </div>
        <div class="field">
          <label>Month <span class="req">*</span></label>
          <div style="display:flex; gap:8px">
            <select v-model.number="month" style="flex:1">
              <option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option>
            </select>
            <input v-model.number="year" type="number" style="width:100px">
          </div>
        </div>
      </div>

      <div style="overflow-x:auto">
        <table class="table">
          <thead><tr><th>Date</th><th>Mileage (km)</th><th>Liters</th><th>Cost $</th><th></th></tr></thead>
          <tbody>
            <tr v-for="(r, i) in records" :key="i">
              <td><input v-model="r.date" type="date"></td>
              <td><input v-model="r.mileage" type="number" min="0"></td>
              <td><input v-model="r.liters" type="number" min="0" step="0.01"></td>
              <td><input v-model="r.cost" type="number" min="0" step="0.01"></td>
              <td><button v-if="records.length > 1" class="remove-btn" @click="records.splice(i, 1)">✕</button></td>
            </tr>
          </tbody>
        </table>
      </div>

      <button class="add-btn" style="margin:12px 0" @click="addRecord">+ Add Record</button>

      <div style="display:flex; justify-content:space-between; align-items:center">
        <div style="display:flex; gap:24px">
          <span>Total Fuel: <strong>{{ Math.round(totalLiters * 100) / 100 }} L</strong></span>
          <span>Total Cost: <strong>${{ Math.round(totalCost * 100) / 100 }}</strong></span>
        </div>
        <button class="btn primary" :disabled="submitting" @click="submit">{{ editId ? '✓ Update Log-Sheet' : 'Submit Log-Sheet' }}</button>
      </div>
    </section>
  </div>
</template>
