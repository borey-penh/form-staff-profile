<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRequestEdit } from '@/composables/useRequestEdit'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const { editId, currentAttachment, loadForEdit, save } = useRequestEdit('Purchase')

const form = ref({ purpose: '', department: '', requiredDate: '', justification: '' })
const items = ref([{ name: '', qty: 1, unitCost: 0 }])
const submitting = ref(false)
const attachment = ref(null)
const fileInput = ref(null)

function clearAttachment() {
  attachment.value = null
  if (fileInput.value) fileInput.value.value = ''
}

onMounted(() => loadForEdit((f) => {
  form.value.purpose = f.purpose ?? ''
  form.value.department = f.department ?? ''
  form.value.requiredDate = f.requiredDate ?? ''
  form.value.justification = f.justification ?? ''
  if (Array.isArray(f.items) && f.items.length) {
    items.value = f.items.map((i) => ({ name: i.name ?? '', qty: i.qty ?? 1, unitCost: i.unitCost ?? 0 }))
  }
}))

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
    await save({
      ...form.value,
      items: valid.map((i) => ({ name: i.name, qty: parseFloat(i.qty) || 0, unitCost: parseFloat(i.unitCost) || 0 })),
    }, attachment.value)
    toast.show(editId.value ? '✓ Purchase request updated' : '✓ Purchase request submitted — status: Pending')
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

    <section class="card" style="max-width:960px">
      <div class="card-head"><h2>Request Information</h2>
        <span v-if="editId" class="help">✏️ Editing request #{{ editId }} — it stays editable until someone approves or rejects it.</span>
      </div>
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

      <div class="total-bar">
        <span>Total Amount</span>
        <strong>${{ total.toFixed(2) }}</strong>
      </div>

      <div class="field" style="margin-top:14px"><label>Justification</label><textarea v-model="form.justification" rows="2"></textarea></div>

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
        <span class="help" style="margin-right:auto">The P.R. number is assigned by finance after review.</span>
        <button class="btn secondary" @click="$router.back()">Cancel</button>
        <button class="btn primary" :disabled="submitting" @click="submit">
          <span v-if="submitting">Saving…</span>
          <span v-else>{{ editId ? '✓ Update Request' : '✓ Submit Request' }}</span>
        </button>
      </div>
    </section>
  </div>
</template>
