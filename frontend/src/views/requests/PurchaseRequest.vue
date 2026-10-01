<script setup>
import { computed, ref } from 'vue'
import { submitRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const form = ref({ purpose: '', department: '', requiredDate: '', justification: '' })
const items = ref([{ name: '', qty: 1, unitCost: 0 }])
const submitting = ref(false)

function lineTotal(it) {
  return (parseFloat(it.qty) || 0) * (parseFloat(it.unitCost) || 0)
}
const total = computed(() => items.value.reduce((s, it) => s + lineTotal(it), 0))

async function submit() {
  const valid = items.value.filter((i) => i.name)
  if (!form.value.purpose || valid.length === 0) {
    return toast.show('Add a purpose and at least one item.')
  }
  submitting.value = true
  try {
    await submitRequest('Purchase', {
      ...form.value,
      items: valid.map((i) => ({ name: i.name, qty: parseFloat(i.qty) || 0, unitCost: parseFloat(i.unitCost) || 0 })),
    })
    toast.show('✓ Purchase request submitted — status: Pending')
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
        <h1>Purchase Request</h1>
        <div class="sub">Request items with quantities and estimated unit costs.</div>
      </div>
    </div>

    <section class="card" style="max-width:820px">
      <div class="card-head"><h2>Request Information</h2></div>
      <div class="field"><label>Purpose <span class="req">*</span></label><textarea v-model="form.purpose" rows="2"></textarea></div>
      <div class="row2">
        <div class="field"><label>Department <span class="req">*</span></label><input v-model="form.department"></div>
        <div class="field"><label>Required Date <span class="req">*</span></label><input v-model="form.requiredDate" type="date"></div>
      </div>

      <div class="card-head" style="margin-top:8px"><h2>Items</h2></div>
      <div style="overflow-x:auto">
        <table class="table">
          <thead><tr><th>Item</th><th style="width:90px">Qty</th><th style="width:130px">Unit Cost $</th><th style="width:110px" class="num">Total</th><th></th></tr></thead>
          <tbody>
            <tr v-for="(it, i) in items" :key="i">
              <td><input v-model="it.name" placeholder="Item description"></td>
              <td><input v-model="it.qty" type="number" min="1"></td>
              <td><input v-model="it.unitCost" type="number" min="0" step="0.01"></td>
              <td class="num">${{ lineTotal(it).toFixed(2) }}</td>
              <td><button v-if="items.length > 1" class="remove-btn" @click="items.splice(i, 1)">✕</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <button class="add-btn" style="margin:12px 0" @click="items.push({ name: '', qty: 1, unitCost: 0 })">+ Add Item</button>

      <div style="display:flex; justify-content:flex-end; padding:12px 14px; background:var(--primary-soft); border-radius:10px; gap:18px">
        <strong>Total Amount: ${{ total.toFixed(2) }}</strong>
      </div>

      <div class="field" style="margin-top:14px"><label>Justification</label><textarea v-model="form.justification" rows="2"></textarea></div>

      <div class="actions">
        <button class="btn primary" :disabled="submitting" @click="submit">Submit Request</button>
      </div>
    </section>
  </div>
</template>
