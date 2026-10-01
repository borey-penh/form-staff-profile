<script setup>
import { computed, onMounted, ref } from 'vue'
import { submitRequest, getLeaveBalances } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const balances = ref([])
const form = ref({ type: 'Annual', startDate: '', endDate: '', reason: '' })
const submitting = ref(false)

onMounted(async () => {
  balances.value = (await getLeaveBalances()).data
})

const days = computed(() => {
  if (!form.value.startDate || !form.value.endDate) return 0
  const d = (new Date(form.value.endDate) - new Date(form.value.startDate)) / 86400000 + 1
  return d > 0 ? Math.round(d) : 0
})

const remaining = computed(() => {
  const b = balances.value.find((x) => x.type === form.value.type)
  return b ? b.remaining : '—'
})

async function submit() {
  if (days.value < 1) return toast.show('Check your dates.')
  submitting.value = true
  try {
    await submitRequest('Leave', { ...form.value, days: days.value })
    toast.show('✓ Leave request submitted — status: Pending')
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
        <h1>Leave Application</h1>
        <div class="sub">Submit a leave request — routed to your supervisor, then HR for approval.</div>
      </div>
    </div>

    <div class="grid2">
      <section class="card">
        <div class="card-head"><h2>New Request</h2></div>

        <div class="field">
          <label>Leave Type <span class="req">*</span></label>
          <select v-model="form.type">
            <option>Annual</option><option>Sick</option><option>Unpaid</option><option>Maternity</option><option>Other</option>
          </select>
        </div>
        <div class="row2">
          <div class="field"><label>Start Date <span class="req">*</span></label><input v-model="form.startDate" type="date"></div>
          <div class="field"><label>End Date <span class="req">*</span></label><input v-model="form.endDate" type="date"></div>
        </div>
        <div class="field">
          <label>Number of Days</label>
          <input :value="days || '—'" disabled>
          <div class="help">Automatically calculated from the dates.</div>
        </div>
        <div class="field">
          <label>Reason</label>
          <textarea v-model="form.reason" rows="3" placeholder="Brief reason for leave…"></textarea>
        </div>

        <div class="actions">
          <button class="btn primary" :disabled="submitting" @click="submit">Submit Request</button>
        </div>
      </section>

      <section class="card">
        <div class="card-head"><h2>Current Leave Balance</h2></div>
        <table v-if="balances.length" class="table">
          <thead><tr><th>Type</th><th class="num">Entitled</th><th class="num">Used</th><th class="num">Remaining</th></tr></thead>
          <tbody>
            <tr v-for="b in balances" :key="b.type">
              <td>{{ b.type }}</td>
              <td class="num">{{ b.entitled }}</td>
              <td class="num">{{ b.used }}</td>
              <td class="num"><strong :style="{ color: b.remaining === 0 ? 'var(--red)' : 'var(--green)' }">{{ b.remaining }}</strong></td>
            </tr>
          </tbody>
        </table>
        <div v-else class="help">No balance data.</div>
      </section>
    </div>
  </div>
</template>
