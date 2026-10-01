<script setup>
import { computed, onMounted, ref } from 'vue'
import { getChangeRequests, reviewChangeRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const toast = useToastStore()
const loading = ref(true)
const items = ref([])
const filter = ref('Pending')
const note = ref({})
const processing = ref(null)

const filtered = computed(() =>
  filter.value ? items.value.filter((r) => r.status === filter.value) : items.value)

const pendingCount = computed(() => items.value.filter((r) => r.status === 'Pending').length)

async function load() {
  loading.value = true
  try {
    items.value = (await getChangeRequests()).data
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function review(r, action) {
  processing.value = r.id
  try {
    const res = await reviewChangeRequest(r.id, action, note.value[r.id] ?? null)
    toast.show('✓ ' + res.message)
    await load()
  } catch (e) {
    toast.show(e.message)
  } finally {
    processing.value = null
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Profile Change Requests</h1>
        <div class="sub">
          Staff suggestions for locked profile fields — approving applies the new value automatically.
          <span v-if="pendingCount" class="badge pending" style="margin-left:8px">{{ pendingCount }} pending</span>
        </div>
      </div>
      <select v-model="filter" style="width:auto">
        <option value="">All</option>
        <option>Pending</option>
        <option>Approved</option>
        <option>Rejected</option>
      </select>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <EmptyState
        v-else-if="!filtered.length"
        title="No change requests"
        :desc="filter === 'Pending' ? 'The queue is clear 🎉' : 'Nothing matches this filter.'"
      />
      <table v-else class="table">
        <thead>
          <tr><th>Staff</th><th>Field</th><th>Current</th><th>Requested</th><th>Reason</th><th>Status</th><th>Review</th></tr>
        </thead>
        <tbody>
          <tr v-for="r in filtered" :key="r.id">
            <td>
              <strong>{{ r.staff }}</strong>
              <div class="help">{{ r.staffId }}</div>
            </td>
            <td>{{ r.fieldLabel }}</td>
            <td style="color:var(--muted)">{{ r.currentValue || '—' }}</td>
            <td><strong>{{ r.requestedValue }}</strong></td>
            <td style="max-width:220px">{{ r.reason }}</td>
            <td>
              <StatusBadge :status="r.status" />
              <div v-if="r.reviewedBy" class="help">by {{ r.reviewedBy }}</div>
            </td>
            <td style="max-width:220px">
              <template v-if="r.status === 'Pending'">
                <input
                  v-model="note[r.id]"
                  class="help"
                  placeholder="Note (optional)"
                  style="margin-bottom:6px; width:100%"
                >
                <div style="display:flex; gap:6px">
                  <button class="btn sm primary" :disabled="processing === r.id" @click="review(r, 'approve')">Approve</button>
                  <button class="btn sm secondary" :disabled="processing === r.id" @click="review(r, 'reject')">Reject</button>
                </div>
              </template>
              <span v-else-if="r.reviewNote" class="help">{{ r.reviewNote }}</span>
              <span v-else class="help">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
