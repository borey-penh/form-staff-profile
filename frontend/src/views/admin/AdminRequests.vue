<script setup>
import { onMounted, ref } from 'vue'
import { getRequests, actOnRequest, getRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import StatusBadge from '@/components/StatusBadge.vue'
import RequestDetailModal from '@/components/RequestDetailModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { formatDate } from '@/utils/format'

const toast = useToastStore()
const auth = useAuthStore()
const items = ref([])
const loading = ref(true)
const detail = ref(null)
const canAct = auth.can('requests.approve')

const filters = ref({ type: '', status: '' })
const TYPES = ['Leave', 'Overtime', 'Timesheet', 'Travel', 'Fuel', 'Purchase', 'Voucher']

async function load() {
  loading.value = true
  try {
    items.value = (await getRequests({
      type: filters.value.type || undefined,
      status: filters.value.status || undefined,
    })).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function open(r) {
  try {
    detail.value = await getRequest(r.id)
  } catch (e) {
    toast.show(e.message)
  }
}

const pending = ref(null) // { request, action } waiting in the note dialog
const ACTION_LABELS = {
  review: 'Review',
  approve: 'Approve',
  reject: 'Reject',
  return: 'Return',
  complete: 'Mark Paid',
}
const ACTION_MESSAGES = {
  review: 'The request will be moved to In Review.',
  approve: 'The request will be marked Approved and the staff notified.',
  reject: 'The request will be marked Rejected and the staff notified.',
  return: 'The request will be sent back to the staff for changes.',
  complete: 'The request will be marked Completed and paid.',
}

function act(r, action) {
  pending.value = { request: r, action }
}

async function confirmAct(note) {
  const { request: r, action } = pending.value
  pending.value = null

  try {
    const res = await actOnRequest(r.id, action, note)
    toast.show(`✓ ${r.type} request #${r.id}: ${res.status}`)
    await load()
  } catch (e) {
    toast.show(e.message)
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Approval Queue</h1>
        <div class="sub">Review, approve, reject or return staff requests. Every action is recorded in the trail.</div>
      </div>
      <div style="display:flex; gap:8px">
        <select v-model="filters.type" @change="load" style="width:auto">
          <option value="">All types</option>
          <option v-for="t in TYPES" :key="t">{{ t }}</option>
        </select>
        <select v-model="filters.status" @change="load" style="width:auto">
          <option value="">All statuses</option>
          <option v-for="s in ['Pending','In Review','Approved','Rejected','Returned','Completed','Cancelled']" :key="s">{{ s }}</option>
        </select>
      </div>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <table v-else-if="items.length" class="table">
        <thead><tr><th>#</th><th>Type</th><th>Staff</th><th>Submitted</th><th>Status</th><th style="width:330px">Actions</th></tr></thead>
        <tbody>
          <tr v-for="r in items" :key="r.id" style="cursor:pointer" @click="open(r)">
            <td>{{ r.id }}</td>
            <td><strong>{{ r.type }}</strong></td>
            <td>{{ r.staff }}<div class="help">{{ r.staffId }}</div></td>
            <td>{{ formatDate(r.submittedAt) }}</td>
            <td><StatusBadge :status="r.status" /></td>
            <td @click.stop>
              <div v-if="canAct" class="btn-row">
                <button v-if="r.status === 'Pending'" class="btn sm soft-review" @click="act(r, 'review')">Review</button>
                <button class="btn sm soft-approve" @click="act(r, 'approve')">Approve</button>
                <button class="btn sm soft-reject" @click="act(r, 'reject')">Reject</button>
                <button class="btn sm secondary" @click="act(r, 'return')">Return</button>
                <button v-if="r.status === 'Approved' && r.type === 'Voucher'" class="btn sm soft-info" @click="act(r, 'complete')">Mark Paid</button>
              </div>
              <span v-else class="help">View only</span>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="help">Nothing in the queue for this filter. 🎉</div>
    </section>

    <!-- Action confirm + note dialog (in-app; window.prompt is blocked in sandboxed iframes) -->
    <ConfirmDialog
      v-if="pending"
      :title="`${ACTION_LABELS[pending.action]} ${pending.request.type} request #${pending.request.id}?`"
      :message="ACTION_MESSAGES[pending.action] ?? ''"
      :confirm-text="ACTION_LABELS[pending.action] ?? 'Confirm'"
      :danger="pending.action === 'reject'"
      with-note
      :note-required="pending.action === 'reject' || pending.action === 'return'"
      note-placeholder="Reason (recorded in the trail)…"
      @confirm="confirmAct"
      @cancel="pending = null"
    />

    <!-- Detail modal -->
    <RequestDetailModal
      v-if="detail"
      :request="detail.request"
      :details="detail.details"
      :trail="detail.trail"
      :title="`${detail.request.type} Request #${detail.request.id} — ${detail.request.staff ?? ''}`"
      @close="detail = null"
    />
  </div>
</template>
