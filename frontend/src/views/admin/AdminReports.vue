<script setup>
import { onMounted, ref } from 'vue'
import { getReports } from '@/services/portalService'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const data = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    data.value = await getReports()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Reports</h1>
        <div class="sub">Organization summary across all portal modules.</div>
      </div>
      <button class="btn secondary" onclick="window.print()">🖨 Print</button>
    </div>

    <LoadingState v-if="loading" />

    <template v-else-if="data">
      <div class="grid3">
        <section class="card">
          <div class="card-head"><h2>👥 Personnel</h2></div>
          <div style="font-size:24px; font-weight:700; margin-bottom:10px">{{ data.personnel.total }} staff</div>
          <div v-for="(n, dept) in data.personnel.byDepartment" :key="dept"
               style="display:flex; justify-content:space-between; padding:6px 0; font-size:13px; border-bottom:1px solid #eef2f7">
            <span>{{ dept }}</span><strong>{{ n }}</strong>
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>🎓 Training</h2></div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Completions</span><strong>{{ data.training.completions }}</strong>
          </div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Outstanding</span><strong>{{ data.training.outstanding }}</strong>
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>🗓️ Leave</h2></div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Approved this year</span><strong>{{ data.leave.approvedThisYear }}</strong>
          </div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Pending</span><strong>{{ data.leave.pending }}</strong>
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>📄 Contracts</h2></div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Active</span><strong>{{ data.contract.active }}</strong>
          </div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Expiring in 30 days</span><strong :style="{ color: data.contract.expiring30 > 0 ? 'var(--red)' : 'var(--ink)' }">{{ data.contract.expiring30 }}</strong>
          </div>
        </section>

        <section class="card">
          <div class="card-head"><h2>💰 Finance</h2></div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Vouchers paid</span><strong>{{ data.finance.vouchersPaid }}</strong>
          </div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Unpaid</span><strong>{{ data.finance.vouchersUnpaid }}</strong>
          </div>
          <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px">
            <span>Total paid</span><strong>${{ data.finance.totalPaid.toFixed(2) }}</strong>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
