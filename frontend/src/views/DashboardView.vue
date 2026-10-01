<script setup>
import { onMounted, ref } from 'vue'
import { getDashboard } from '@/services/portalService'
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import { useAuthStore } from '@/stores/auth'
import { formatDate } from '@/utils/format'

const auth = useAuthStore()
const data = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    data.value = await getDashboard()
  } finally {
    loading.value = false
  }
})

const quickActions = [
  { label: 'Apply for Leave', to: '/leave', icon: '<path d="M8 2v4M16 2v4"/><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 11h18"/>' },
  { label: 'Request Overtime', to: '/overtime', icon: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>' },
  { label: 'Travel Application', to: '/travel', icon: '<path d="M21 3 3 10.5l6 2.5L21 3z"/><path d="M21 3 11.5 21l-2.5-7.5L21 3z"/>' },
  { label: 'Purchase Request', to: '/purchase', icon: '<circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/><path d="M3 4h2l2.6 12h10.8L21 8H6"/>' },
  { label: 'Fuel Log-Sheet', to: '/fuel', icon: '<path d="M3 22V7a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v15"/><path d="M2 22h13"/><path d="M14 11h3a2 2 0 0 1 2 2v4a2 2 0 0 0 2 2 2 2 0 0 0 2-2v-6l-3-4"/><path d="M6 9h5"/>' },
  { label: 'Submit Voucher', to: '/vouchers', icon: '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>' },
]
</script>

<template>
  <div>
    <!-- Welcome banner -->
    <section class="welcome-banner">
      <div>
        <h1>Welcome, {{ auth.fullName }}</h1>
        <p>Here's an overview of your information and current activities.</p>
      </div>
      <RouterLink to="/profile" class="wb-btn">Complete your profile</RouterLink>
    </section>

    <LoadingState v-if="loading" variant="card" />

    <template v-else-if="data">
      <!-- Stats -->
      <div class="grid4">
        <div class="stat-card">
          <div class="label">Profile Completion</div>
          <div class="value">{{ data.stats.profileCompletion }}<span class="unit">%</span></div>
          <div class="progress"><div :style="{ width: data.stats.profileCompletion + '%' }"></div></div>
          <RouterLink class="stat-link" to="/profile">View profile →</RouterLink>
        </div>
        <div class="stat-card">
          <div class="label">Training Progress</div>
          <div class="value">{{ data.stats.training.done }}<span class="unit">/ {{ data.stats.training.total }}</span></div>
          <div class="progress"><div :style="{ width: (data.stats.training.total ? data.stats.training.done / data.stats.training.total * 100 : 0) + '%' }"></div></div>
          <RouterLink class="stat-link" to="/training">View training →</RouterLink>
        </div>
        <div class="stat-card">
          <div class="label">Active Contract</div>
          <div class="value" style="font-size: 18px">{{ data.stats.contract?.type ?? 'None' }}</div>
          <div class="help" v-if="data.stats.contract">
            {{ formatDate(data.stats.contract.startDate) }} – {{ formatDate(data.stats.contract.endDate) }}
          </div>
          <RouterLink class="stat-link" to="/contracts">View contract →</RouterLink>
        </div>
        <div class="stat-card">
          <div class="label">Compliance Status</div>
          <div class="value">{{ data.stats.compliance.signed }}<span class="unit">/ {{ data.stats.compliance.total }}</span></div>
          <div class="progress"><div :style="{ width: (data.stats.compliance.total ? data.stats.compliance.signed / data.stats.compliance.total * 100 : 0) + '%' }"></div></div>
          <RouterLink class="stat-link" to="/compliances">View compliance →</RouterLink>
        </div>
      </div>

      <!-- Quick actions -->
      <section class="card">
        <div class="card-head"><h2>Quick Actions</h2></div>
        <div class="qa-grid">
          <RouterLink v-for="q in quickActions" :key="q.to" class="qa-item" :to="q.to">
            <span class="qa-ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" v-html="q.icon"></svg>
            </span>
            <span>{{ q.label }}</span>
          </RouterLink>
        </div>
      </section>

      <div class="grid2">
        <!-- Pending requests -->
        <section class="card">
          <div class="card-head"><h2>My Recent Requests</h2><div class="spacer"></div><RouterLink to="/requests">View all →</RouterLink></div>
          <div v-if="data.pendingRequests.length" class="table-wrap">
            <table class="table">
              <thead><tr><th>Type</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <tr v-for="r in data.pendingRequests" :key="r.id">
                  <td><strong>{{ r.type }}</strong></td>
                  <td>{{ formatDate(r.submittedAt) }}</td>
                  <td><StatusBadge :status="r.status" /></td>
                  <td><RouterLink class="btn sm secondary" :to="'/requests'">View</RouterLink></td>
                </tr>
              </tbody>
            </table>
          </div>
          <EmptyState v-else title="No pending requests" desc="Apply for leave or submit a request to get started.">
            <RouterLink class="btn sm primary" to="/leave">Apply for leave</RouterLink>
          </EmptyState>
        </section>

        <!-- Upcoming trainings -->
        <section class="card">
          <div class="card-head"><h2>Upcoming Training</h2><div class="spacer"></div><RouterLink to="/training">View all →</RouterLink></div>
          <div v-if="data.upcomingTrainings.length">
            <div v-for="t in data.upcomingTrainings" :key="t.id" class="feed-row">
              <div class="date-chip">
                <div class="d">{{ t.dueDate ? t.dueDate.slice(8) : '—' }}</div>
                <div class="m">{{ t.dueDate ? t.dueDate.slice(5, 7) : '' }}</div>
              </div>
              <div style="flex:1; min-width:0">
                <div style="font-weight:600; font-size:13px">{{ t.title }}</div>
                <div class="help">Due: {{ formatDate(t.dueDate) }}</div>
              </div>
              <span class="badge info">Upcoming</span>
            </div>
          </div>
          <EmptyState v-else title="No upcoming trainings" desc="You're all caught up with assigned trainings." />
        </section>
      </div>

      <div class="grid2">
        <!-- Leave balance -->
        <section class="card">
          <div class="card-head"><h2>Leave Balance</h2><div class="spacer"></div><RouterLink to="/leave">Apply →</RouterLink></div>
          <div v-if="data.leaveBalances.length">
            <div v-for="b in data.leaveBalances" :key="b.type" class="balance-row">
              <div class="bal-type">{{ b.type }}</div>
              <div class="progress" style="flex:1"><div :style="{ width: (b.entitled ? Math.min(100, b.remaining / b.entitled * 100) : 0) + '%' }"></div></div>
              <div class="bal-nums">{{ b.remaining }} <span>of {{ b.entitled }} left</span></div>
            </div>
          </div>
          <EmptyState v-else title="No leave balance yet" desc="Your leave entitlements will appear here once configured." />
        </section>

        <!-- Recent activities -->
        <section class="card">
          <div class="card-head"><h2>Recent Activities</h2></div>
          <div v-if="data.activities.length">
            <div v-for="a in data.activities" :key="a.id" class="feed-row">
              <span class="act-dot"></span>
              <div style="flex:1; font-size:13px">{{ a.message }}</div>
              <div class="help" style="white-space:nowrap">{{ formatDate(a.at) }}</div>
            </div>
          </div>
          <EmptyState v-else title="No recent activity" desc="Your actions will show up here." />
        </section>
      </div>
    </template>
  </div>
</template>
