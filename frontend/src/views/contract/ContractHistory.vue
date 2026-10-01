<script setup>
import { onMounted, ref } from 'vue'
import { getContracts } from '@/services/portalService'
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import { formatDate } from '@/utils/format'

const items = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    items.value = (await getContracts()).data
  } finally {
    loading.value = false
  }
})

function expiringSoon(c) {
  if (!c.endDate || c.status !== 'Active') return false
  const end = new Date(c.endDate)
  const days = (end - Date.now()) / 86400000
  return days >= 0 && days <= 60
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Contract History</h1>
        <div class="sub">All contracts on record — probationary, full-time, part-time, and more.</div>
      </div>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />

      <div v-else-if="items.length" class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Contract</th><th>Position</th><th>Start Date</th><th>End Date</th><th>Salary</th><th>Status</th><th>File</th></tr>
          </thead>
          <tbody>
            <tr v-for="c in items" :key="c.id">
              <td>
                <strong>{{ c.type }}</strong>
                <div v-if="expiringSoon(c)" class="help" style="color:var(--amber); font-weight:600">⚠ Expiring soon</div>
              </td>
              <td>{{ c.position }}<div class="help" v-if="c.department">{{ c.department }}</div></td>
              <td>{{ formatDate(c.startDate) }}</td>
              <td>{{ formatDate(c.endDate) }}</td>
              <td>{{ c.salary ? '$' + c.salary.toLocaleString() : '—' }}</td>
              <td><StatusBadge :status="c.status" /></td>
              <td><a v-if="c.fileUrl" :href="c.fileUrl" target="_blank">View</a><span v-else class="help">—</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState
        v-else
        title="No contracts on record yet"
        desc="Your contracts will appear here once HR adds them."
      />
    </section>
  </div>
</template>
