<script setup>
import { onMounted, ref } from 'vue'
import { getTrainings } from '@/services/portalService'
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import { formatDate } from '@/utils/format'

const items = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    items.value = (await getTrainings()).data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Staff Online Training</h1>
        <div class="sub">Complete assigned courses — each ends with a quiz and certificate.</div>
      </div>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />

      <div v-else-if="items.length" class="table-wrap">
        <table class="table">
          <thead><tr><th>Training</th><th>Progress</th><th>Score</th><th>Due</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-for="t in items" :key="t.id">
              <td><strong>{{ t.title }}</strong></td>
              <td style="min-width:130px">
                <div class="progress"><div :style="{ width: t.progress + '%' }"></div></div>
                <div class="help">{{ t.progress }}%</div>
              </td>
              <td>{{ t.score != null ? t.score + '%' : '—' }}</td>
              <td>{{ formatDate(t.dueDate) }}</td>
              <td><StatusBadge :status="t.status" /></td>
              <td>
                <RouterLink v-if="t.status !== 'Complete'" class="btn sm primary" :to="'/training/' + t.id">
                  {{ t.progress > 0 ? 'Continue' : 'Start' }}
                </RouterLink>
                <RouterLink v-else class="btn sm secondary" :to="'/training/' + t.id + '/certificate'">Certificate</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState
        v-else
        title="No trainings assigned yet"
        desc="Assigned courses will appear here."
      />
    </section>
  </div>
</template>
