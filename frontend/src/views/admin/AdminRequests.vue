<script setup>
import { onMounted, ref } from 'vue'
import { getRequests, actOnRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'
import { formatDate } from '@/utils/format'

const toast = useToastStore()
const items = ref([])
const loading = ref(true)

const filters = ref({ type: '', status: 'Pending' })
const TYPES = ['Leave', 'Overtime', 'Timesheet', 'Travel', 'Fuel', 'Purchase', 'Voucher']

async function load() {
  loading.value = true
  try {
    items.value = (await getRequests({
      type: filters.value.type || undefined,
      status: filters.value.status || undefined,
    })).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function act(r, action) {
  const note = action === 'reject' || action === 'return'
    ? prompt(`${action} note (required):`)
    : prompt(`${action} note (optional):`) || undefined

  if ((action === 'reject' || action === 'return') && !note) return

  try {
    const res = await actOnRequest(r.id, action, note)
    toast.show(`✓ ${r.type} request #${r.id}: ${res.status}`)
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
        <h1>Approval Queue</h1>
        <div class="sub">Review, approve, reject or return staff requests. Every action is recorded in the trail.</div>
      </div>
      <div style="display:flex; gap:8px">
        <select v-model="filters.type" @change="load" style="width:auto">
          <option value="">All types</option>
          <option v-for="t in TYPES" :key="t">{{ t }}</option>
        </select>
        <select v-model="filters.status" @change="load" style="width:auto">
          <option value="">All statuses</option>
          <option v-for="s in ['Pending','In Review','Approved','Rejected','Returned','Completed','Cancelled']" :key="s">{{ s }}</option>
        </select>
      </div>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <table v-else-if="items.length" class="table">
        <thead><tr><th>#</th><th>Type</th><th>Staff</th><th>Submitted</th><th>Status</th><th style="width:290px">Actions</th></tr></thead>
        <tbody>
          <tr v-for="r in items" :key="r.id">
            <td>{{ r.id }}</td>
            <td><strong>{{ r.type }}</strong></td>
            <td>{{ r.staff }}<div class="help">{{ r.staffId }}</div></td>
            <td>{{ formatDate(r.submittedAt) }}</td>
            <td><StatusBadge :status="r.status" /></td>
            <td>
              <button v-if="r.status === 'Pending'" class="btn sm secondary" @click="act(r, 'review')">Review</button>
              <button class="btn sm success" @click="act(r, 'approve')">Approve</button>
              <button class="btn sm danger" @click="act(r, 'reject')">Reject</button>
              <button class="btn sm secondary" @click="act(r, 'return')">Return</button>
              <button v-if="r.status === 'Approved' && r.type === 'Voucher'" class="btn sm primary" @click="act(r, 'complete')">Mark Paid</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="help">Nothing in the queue for this filter. 🎉</div>
    </section>
  </div>
</template>
