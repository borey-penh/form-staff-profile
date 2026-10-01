<script setup>
import { computed, ref } from 'vue'
import { submitRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const form = ref({
  date: new Date().toISOString().slice(0, 10),
  expenseType: 'Travel',
  description: '',
  paymentMethod: 'Cash',
})
const lines = ref([{ description: '', amount: 0 }])
const submitting = ref(false)

const total = computed(() => lines.value.reduce((s, l) => s + (parseFloat(l.amount) || 0), 0))
const voucherNo = `PV-${new Date().getFullYear()}-##### (auto)`

async function submit() {
  const valid = lines.value.filter((l) => l.description && parseFloat(l.amount) > 0)
  if (valid.length === 0) return toast.show('Add at least one line with description and amount.')

  submitting.value = true
  try {
    await submitRequest('Voucher', {
      ...form.value,
      lines: valid.map((l) => ({ description: l.description, amount: parseFloat(l.amount) || 0 })),
    })
    toast.show('✓ Voucher submitted — routed to Manager → Finance')
    router.push('/requests')
  } catch (e) {
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Payment / Expense Voucher</h1>
        <div class="sub">Voucher number is generated automatically on submission.</div>
      </div>
    </div>

    <section class="card" style="max-width:760px">
      <div class="row3">
        <div class="field"><label>Voucher No.</label><input :value="voucherNo" disabled></div>
        <div class="field"><label>Date <span class="req">*</span></label><input v-model="form.date" type="date"></div>
        <div class="field">
          <label>Expense Type <span class="req">*</span></label>
          <select v-model="form.expenseType">
            <option>Travel</option><option>Meals</option><option>Office Supplies</option><option>Transport</option><option>Other</option>
          </select>
        </div>
      </div>

      <div class="field"><label>Description</label><textarea v-model="form.description" rows="2"></textarea></div>

      <div class="card-head"><h2>Amount Lines</h2></div>
      <table class="table">
        <thead><tr><th>Description</th><th style="width:150px">Amount $</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(l, i) in lines" :key="i">
            <td><input v-model="l.description" placeholder="e.g. Transport"></td>
            <td><input v-model="l.amount" type="number" min="0" step="0.01"></td>
            <td><button v-if="lines.length > 1" class="remove-btn" @click="lines.splice(i, 1)">✕</button></td>
          </tr>
        </tbody>
      </table>
      <button class="add-btn" style="margin:12px 0" @click="lines.push({ description: '', amount: 0 })">+ Add Line</button>

      <div style="display:flex; justify-content:flex-end; padding:12px 14px; background:var(--primary-soft); border-radius:10px; margin-bottom:14px">
        <strong>Total Amount: ${{ total.toFixed(2) }}</strong>
      </div>

      <div class="field">
        <label>Payment Method <span class="req">*</span></label>
        <div style="display:flex; gap:18px">
          <label class="checkline" style="margin:0"><input type="radio" value="Cash" v-model="form.paymentMethod"> Cash</label>
          <label class="checkline" style="margin:0"><input type="radio" value="Bank Transfer" v-model="form.paymentMethod"> Bank Transfer</label>
        </div>
      </div>

      <div class="actions">
        <button class="btn primary" :disabled="submitting" @click="submit">Submit Voucher</button>
      </div>
    </section>
  </div>
</template>
