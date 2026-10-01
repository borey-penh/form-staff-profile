<script setup>
import { onMounted, ref } from 'vue'
import { getAdminVouchers, payVoucher } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { formatDate } from '@/utils/format'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const toast = useToastStore()
const items = ref([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    items.value = (await getAdminVouchers()).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function pay(v) {
  await payVoucher(v.id)
  toast.show(`✓ Voucher ${v.voucherNo} marked paid.`)
  await load()
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Finance — Vouchers</h1>
        <div class="sub">Review submitted vouchers and record payments.</div>
      </div>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <table v-else-if="items.length" class="table">
        <thead><tr><th>Voucher No.</th><th>Date</th><th>Expense Type</th><th class="num">Amount</th><th>Method</th><th>Payment</th><th></th></tr></thead>
        <tbody>
          <tr v-for="v in items" :key="v.id">
            <td><strong>{{ v.voucherNo }}</strong></td>
            <td>{{ formatDate(v.date) }}</td>
            <td>{{ v.expenseType }}</td>
            <td class="num">${{ v.total.toFixed(2) }}</td>
            <td>{{ v.paymentMethod }}</td>
            <td>
              <span class="badge" :class="v.paymentStatus === 'Paid' ? 'approved' : 'pending'">{{ v.paymentStatus }}</span>
            </td>
            <td>
              <button v-if="v.paymentStatus !== 'Paid'" class="btn sm success" @click="pay(v)">Mark Paid</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="help">No vouchers submitted yet.</div>
    </section>
  </div>
</template>
