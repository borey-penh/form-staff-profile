<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRequestEdit } from '@/composables/useRequestEdit'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const { editId, currentAttachment, loadForEdit, save } = useRequestEdit('Voucher')

const form = ref({
  date: new Date().toISOString().slice(0, 10),
  expenseType: 'Travel',
  description: '',
  paymentMethod: 'Cash',
})
const lines = ref([{ description: '', amount: 0 }])
const submitting = ref(false)
const attachment = ref(null)
const fileInput = ref(null)

function clearAttachment() {
  attachment.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const total = computed(() => lines.value.reduce((s, l) => s + (parseFloat(l.amount) || 0), 0))
const voucherNo = `PV-${new Date().getFullYear()}-##### (auto)`
const savedVoucherNo = ref('') // real number shown when editing an existing voucher

onMounted(() => loadForEdit((f) => {
  if (f.voucherNo) savedVoucherNo.value = f.voucherNo
  if (f.date) form.value.date = f.date
  if (f.expenseType) form.value.expenseType = f.expenseType
  form.value.description = f.description ?? ''
  if (f.paymentMethod) form.value.paymentMethod = f.paymentMethod
  if (Array.isArray(f.lines) && f.lines.length) {
    lines.value = f.lines.map((l) => ({ description: l.description ?? '', amount: l.amount ?? 0 }))
  }
}))

async function submit() {
  const valid = lines.value.filter((l) => l.description && parseFloat(l.amount) > 0)
  if (valid.length === 0) return toast.show('Add at least one line with description and amount.')

  submitting.value = true
  try {
    await save({
      ...form.value,
      lines: valid.map((l) => ({ description: l.description, amount: parseFloat(l.amount) || 0 })),
    }, attachment.value)
    toast.show(editId.value ? '✓ Voucher updated' : '✓ Voucher submitted — routed to Manager → Finance')
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
      <div v-if="editId" class="help" style="margin-bottom:12px">✏️ Editing voucher request #{{ editId }} — it stays editable until someone approves or rejects it.</div>
      <div class="row3">
        <div class="field"><label>Voucher No.</label><input :value="editId ? savedVoucherNo : voucherNo" disabled></div>
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

      <div class="field">
        <label>Attachment (optional — PDF/Image, max 5MB)</label>
        <div class="upload-box" style="margin:0">
          <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" @change="e => attachment = e.target.files[0]">
          <button v-if="attachment" class="btn sm secondary" type="button" @click="clearAttachment">✕ Remove</button>
        </div>
        <div v-if="attachment" class="help" style="margin-top:4px">📎 {{ attachment.name }}</div>
        <div v-else-if="currentAttachment" class="help" style="margin-top:4px">
          📎 <a :href="currentAttachment" target="_blank" rel="noopener">Current attachment</a> kept — choose a file to replace it.
        </div>
      </div>

      <div class="actions">
        <button class="btn primary" :disabled="submitting" @click="submit">{{ editId ? '✓ Update Voucher' : 'Submit Voucher' }}</button>
      </div>
    </section>
  </div>
</template>
