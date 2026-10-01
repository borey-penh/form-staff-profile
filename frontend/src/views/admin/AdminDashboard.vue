<script setup>
import { onMounted, ref } from 'vue'
import { getAdminDashboard } from '@/services/portalService'
import { formatDate } from '@/utils/format'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const data = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    data.value = await getAdminDashboard()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Personnel Dashboard</h1>
        <div class="sub">Organization-wide overview for HR and management.</div>
      </div>
    </div>

    <LoadingState v-if="loading" />

    <template v-else-if="data">
      <div class="grid3">
        <div class="stat-card">
          <div class="label">Total Staff</div>
          <div class="value">{{ data.totalStaff }}</div>
          <RouterLink to="/admin/staff">Manage Staff →</RouterLink>
        </div>
        <div class="stat-card">
          <div class="label">Active Contracts</div>
          <div class="value">{{ data.activeContracts }}</div>
        </div>
        <div class="stat-card">
          <div class="label">Pending Requests</div>
          <div class="value">{{ data.pendingRequests }}</div>
          <RouterLink to="/admin/requests">Approval Queue →</RouterLink>
        </div>
      </div>

      <div class="grid2">
        <section class="card">
          <div class="card-head"><h2>⚠️ Contracts Expiring Soon (60 days)</h2></div>
          <div v-if="data.expiringContracts.length">
            <div v-for="c in data.expiringContracts" :key="c.id"
                 style="display:flex; justify-content:space-between; padding:9px 0; border-bottom:1px solid #eef2f7; font-size:13px">
              <span><strong>{{ c.staffId }}</strong> — {{ c.staff }}</span>
              <span><span class="badge pending">{{ formatDate(c.endDate) }}</span></span>
            </div>
          </div>
          <div v-else class="help">No contracts expiring in the next 60 days. ✓</div>
        </section>

        <section class="card">
          <div class="card-head"><h2>⏳ Pending Requests by Type</h2></div>
          <div v-if="Object.keys(data.pendingByType).length">
            <div v-for="(count, type) in data.pendingByType" :key="type"
                 style="display:flex; justify-content:space-between; padding:9px 0; border-bottom:1px solid #eef2f7; font-size:13px">
              <span>{{ type }}</span>
              <strong :style="{ color: count > 5 ? 'var(--red)' : 'var(--ink)' }">{{ count }}</strong>
            </div>
          </div>
          <div v-else class="help">Nothing waiting for approval. 🎉</div>
        </section>
      </div>
    </template>
  </div>
</template>
