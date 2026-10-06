<script setup>
import { computed, onMounted, ref } from 'vue'
import { getLeaveBalances } from '@/services/portalService'
import { useRequestEdit } from '@/composables/useRequestEdit'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const { editId, currentAttachment, loadForEdit, save } = useRequestEdit('Leave')

const balances = ref([])
const form = ref({ type: 'Annual', startDate: '', endDate: '', reason: '' })
const submitting = ref(false)
const attachment = ref(null)
const fileInput = ref(null)

const leaveTypes = ['Annual', 'Sick Leave', 'Special', 'Compassionate', 'Time in Lieu', 'Paternity', 'Unpaid', 'Study']
// Keep legacy types selectable when an old request is opened for editing.
const leaveTypeOptions = computed(() =>
  form.value.type && !leaveTypes.includes(form.value.type)
    ? [...leaveTypes, form.value.type]
    : leaveTypes
)

function clearAttachment() {
  attachment.value = null
  if (fileInput.value) fileInput.value.value = ''
}

onMounted(async () => {
  balances.value = (await getLeaveBalances()).data
  await loadForEdit((f) => {
    if (f.type) form.value.type = f.type
    form.value.startDate = f.startDate ?? ''
    form.value.endDate = f.endDate ?? ''
    form.value.reason = f.reason ?? ''
  })
})

const days = computed(() => {
  if (!form.value.startDate || !form.value.endDate) return 0
  const d = (new Date(form.value.endDate) - new Date(form.value.startDate)) / 86400000 + 1
  return d > 0 ? Math.round(d) : 0
})

const remaining = computed(() => {
  const b = balances.value.find((x) => x.type === form.value.type)
  return b ? b.remaining : '—'
})

async function submit() {
  if (days.value < 1) return toast.show('Check your dates.')
  submitting.value = true
  try {
    await save({ ...form.value, days: days.value }, attachment.value)
    toast.show(editId.value ? '✓ Leave request updated' : '✓ Leave request submitted — status: Pending')
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
        <h1>Leave Application</h1>
        <div class="sub">Submit a leave request — routed to your supervisor, then HR for approval.</div>
      </div>
    </div>

    <div class="grid2">
      <section class="card">
        <div class="card-head"><h2>{{ editId ? `Edit Request #${editId}` : 'New Request' }}</h2>
          <span v-if="editId" class="help">✏️ Editing — your request stays editable until someone approves or rejects it.</span>
        </div>

        <div class="field">
          <label>Leave Type <span class="req">*</span></label>
          <select v-model="form.type">
            <option v-for="t in leaveTypeOptions" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div class="row2">
          <div class="field"><label>Start Date <span class="req">*</span></label><input v-model="form.startDate" type="date"></div>
          <div class="field"><label>End Date <span class="req">*</span></label><input v-model="form.endDate" type="date"></div>
        </div>
        <div class="field">
          <label>Number of Days</label>
          <input :value="days || '—'" disabled>
          <div class="help">Automatically calculated from the dates.</div>
        </div>
        <div class="field">
          <label>Reason</label>
          <textarea v-model="form.reason" rows="3" placeholder="Brief reason for leave…"></textarea>
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
          <button class="btn primary" :disabled="submitting" @click="submit">{{ editId ? '✓ Update Request' : 'Submit Request' }}</button>
        </div>
      </section>

      <section class="card">
        <div class="card-head"><h2>Current Leave Balance</h2></div>
        <table v-if="balances.length" class="table">
          <thead><tr><th>Type</th><th class="num">Entitled</th><th class="num">Used</th><th class="num">Remaining</th></tr></thead>
          <tbody>
            <tr v-for="b in balances" :key="b.type">
              <td>{{ b.type }}</td>
              <td class="num">{{ b.entitled }}</td>
              <td class="num">{{ b.used }}</td>
              <td class="num"><strong :style="{ color: b.remaining === 0 ? 'var(--red)' : 'var(--green)' }">{{ b.remaining }}</strong></td>
            </tr>
          </tbody>
        </table>
        <div v-else class="help">No balance data.</div>
      </section>
    </div>
  </div>
</template>
