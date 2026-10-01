<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { getTraining } from '@/services/portalService'
import { useAuthStore } from '@/stores/auth'
import { formatDate } from '@/utils/format'

const route = useRoute()
const auth = useAuthStore()
const data = ref(null)

onMounted(async () => {
  data.value = await getTraining(route.params.id)
})

const info = computed(() => data.value?.assignment ?? {})
const title = computed(() => data.value?.training?.title ?? '')
</script>

<template>
  <div style="max-width: 760px; margin: 0 auto">
    <div class="card" style="text-align:center; padding:46px 36px; border:2px solid var(--primary)">
      <div style="font-size:40px">🎓</div>
      <h1 style="font-size:22px; margin:8px 0 2px">Certificate of Completion</h1>
      <div class="help" style="margin-bottom:24px">Personnel Portal — Staff Online Training</div>

      <p style="font-size:13.5px; color:var(--text)">This certifies that</p>
      <div style="font-size:22px; font-weight:700; margin:8px 0">{{ auth.fullName }}</div>
      <p style="font-size:13.5px; color:var(--text)">has successfully completed the training</p>
      <div style="font-size:17px; font-weight:600; color:var(--primary); margin:8px 0 20px">{{ title }}</div>

      <div v-if="data" class="grid3" style="text-align:center; margin-top:8px">
        <div>
          <div class="help">Score</div>
          <strong>{{ info.score }}%</strong>
        </div>
        <div>
          <div class="help">Completed</div>
          <strong>{{ formatDate(info.completedAt) }}</strong>
        </div>
        <div>
          <div class="help">Staff ID</div>
          <strong>{{ auth.user?.staffId }}</strong>
        </div>
      </div>

      <div style="margin-top:34px; display:flex; justify-content:center; gap:60px">
        <div>
          <div style="border-top:1px solid var(--ink); padding-top:6px; font-size:12px; min-width:180px">HR Manager</div>
        </div>
        <div>
          <div style="border-top:1px solid var(--ink); padding-top:6px; font-size:12px; min-width:180px">Date</div>
        </div>
      </div>
    </div>

    <div class="actions" style="max-width:760px">
      <RouterLink class="btn secondary" :to="'/training/' + route.params.id">← Back to training</RouterLink>
      <button class="btn primary" onclick="window.print()">🖨 Print Certificate</button>
    </div>
  </div>
</template>
