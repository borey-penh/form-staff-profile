<script setup>
import { onMounted, ref } from 'vue'
import { getRequests, getRequest } from '@/services/portalService'
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import RequestDetailModal from '@/components/RequestDetailModal.vue'
import { useToastStore } from '@/stores/toast'
import { formatDate } from '@/utils/format'

const toast = useToastStore()
const items = ref([])
const loading = ref(true)
const detail = ref(null)

const filters = ref({ type: '', status: '' })
const TYPES = ['Leave', 'Overtime', 'Timesheet', 'Travel', 'Fuel', 'Purchase', 'Voucher']
const STATUSES = ['Draft', 'Pending', 'In Review', 'Approved', 'Rejected', 'Returned', 'Completed', 'Cancelled']

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

async function open(r) {
  try {
    detail.value = await getRequest(r.id)
  } catch (e) {
    toast.show(e.message)
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>My Requests</h1>
        <div class="sub">All your submissions with their approval workflow status.</div>
      </div>
      <div style="display:flex; gap:8px">
        <select v-model="filters.type" @change="load" style="width:auto">
          <option value="">All types</option>
          <option v-for="t in TYPES" :key="t">{{ t }}</option>
        </select>
        <select v-model="filters.status" @change="load" style="width:auto">
          <option value="">All statuses</option>
          <option v-for="s in STATUSES" :key="s">{{ s }}</option>
        </select>
      </div>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <div v-else-if="items.length" class="table-wrap">
        <table class="table">
        <thead><tr><th>#</th><th>Type</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in items" :key="r.id">
            <td>{{ r.id }}</td>
            <td><strong>{{ r.type }}</strong></td>
            <td>{{ formatDate(r.submittedAt) }}</td>
            <td><StatusBadge :status="r.status" /></td>
            <td><button class="btn sm secondary" @click="open(r)">View</button></td>
          </tr>
        </tbody>
      </table>
      </div>
      <EmptyState
        v-else
        title="No requests yet"
        desc="Your submissions and their approval status will appear here."
      >
        <RouterLink class="btn sm primary" to="/leave">Apply for leave</RouterLink>
      </EmptyState>
    </section>

    <!-- Detail modal -->
    <RequestDetailModal
      v-if="detail"
      :request="detail.request"
      :details="detail.details"
      :trail="detail.trail"
      :title="`${detail.request.type} Request #${detail.request.id}`"
      @close="detail = null"
    />
  </div>
</template>
