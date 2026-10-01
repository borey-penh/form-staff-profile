<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { getAdminStaffDetail, verifyDocument, createContract } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'
import { formatDate } from '@/utils/format'

const route = useRoute()
const toast = useToastStore()
const data = ref(null)
const loading = ref(true)
const showContract = ref(false)
const submitting = ref(false)

const contractForm = ref({
  type: 'Full-Time', position: '', department: '',
  startDate: '', endDate: '', salary: '',
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    data.value = await getAdminStaffDetail(route.params.id)
  } finally {
    loading.value = false
  }
}

async function verify(docId, status) {
  await verifyDocument(docId, status)
  toast.show(`Document marked ${status}.`)
  await load()
}

async function saveContract() {
  submitting.value = true
  try {
    await createContract({
      userId: Number(route.params.id),
      ...contractForm.value,
      salary: contractForm.value.salary ? parseFloat(contractForm.value.salary) : null,
      endDate: contractForm.value.endDate || null,
    })
    toast.show('✓ Contract created.')
    showContract.value = false
    await load()
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
        <h1>{{ data?.user?.fullName ?? 'Staff' }}</h1>
        <div class="sub" v-if="data">
          {{ data.user.staffId }} · {{ data.user.position ?? '—' }} · {{ data.user.department ?? 'Unassigned' }}
        </div>
      </div>
      <div style="display:flex; gap:8px">
        <RouterLink class="btn secondary" to="/admin/staff">← Back</RouterLink>
        <button class="btn primary" @click="showContract = true">+ Add Contract</button>
      </div>
    </div>

    <LoadingState v-if="loading" />

    <template v-else-if="data">
      <div class="grid2">
        <section class="card">
          <div class="card-head"><h2>👤 Personal Information</h2></div>
          <table class="table">
            <tbody>
              <tr><td class="help">Email</td><td>{{ data.user.email }}</td></tr>
              <tr><td class="help">Phone</td><td>{{ data.user.phone ?? '—' }}</td></tr>
              <tr><td class="help">Gender</td><td>{{ data.user.gender ?? '—' }}</td></tr>
              <tr><td class="help">Date of Birth</td><td>{{ formatDate(data.user.dob) }}</td></tr>
              <tr><td class="help">Nationality</td><td>{{ data.user.nationality ?? '—' }}</td></tr>
              <tr><td class="help">ID / Passport</td><td>{{ data.user.nid ?? '—' }}</td></tr>
              <tr><td class="help">Marital Status</td><td>{{ data.user.marital ?? '—' }}</td></tr>
              <tr><td class="help">Address</td><td>{{ data.user.address ?? '—' }}</td></tr>
            </tbody>
          </table>
        </section>

        <section class="card">
          <div class="card-head"><h2>📄 Documents ({{ data.documents.length }})</h2></div>
          <table v-if="data.documents.length" class="table">
            <thead><tr><th>Type</th><th>File</th><th>Status</th><th>Verify</th></tr></thead>
            <tbody>
              <tr v-for="d in data.documents" :key="d.id">
                <td>{{ d.type }}</td>
                <td><a :href="d.url" target="_blank">{{ d.originalName }}</a></td>
                <td><StatusBadge :status="d.status" /></td>
                <td>
                  <button class="btn sm success" @click="verify(d.id, 'Verified')">✓</button>
                  <button class="btn sm danger" @click="verify(d.id, 'Rejected')">✕</button>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="help">No documents uploaded.</div>
        </section>
      </div>

      <section class="card">
        <div class="card-head"><h2>📄 Contract History</h2></div>
        <table v-if="data.contracts.length" class="table">
          <thead><tr><th>Type</th><th>Position</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="c in data.contracts" :key="c.id">
              <td><strong>{{ c.type }}</strong></td>
              <td>{{ c.position }}</td>
              <td>{{ formatDate(c.startDate) }}</td>
              <td>{{ formatDate(c.endDate) }}</td>
              <td><StatusBadge :status="c.status" /></td>
            </tr>
          </tbody>
        </table>
        <div v-else class="help">No contracts yet.</div>
      </section>

      <!-- Contract modal -->
      <div class="modal-overlay" :class="{ show: showContract }">
        <div class="modal" v-if="showContract" style="max-width:520px">
          <h3>Add Contract</h3>
          <div class="row2">
            <div class="field">
              <label>Contract Type <span class="req">*</span></label>
              <select v-model="contractForm.type">
                <option>Probationary</option><option>Full-Time</option><option>Part-Time</option>
                <option>Intermittent</option><option>Volunteer</option><option>Internship</option>
              </select>
            </div>
            <div class="field"><label>Position <span class="req">*</span></label><input v-model="contractForm.position"></div>
          </div>
          <div class="row2">
            <div class="field"><label>Department</label><input v-model="contractForm.department"></div>
            <div class="field"><label>Salary $</label><input v-model="contractForm.salary" type="number" min="0"></div>
          </div>
          <div class="row2">
            <div class="field"><label>Start Date <span class="req">*</span></label><input v-model="contractForm.startDate" type="date"></div>
            <div class="field"><label>End Date</label><input v-model="contractForm.endDate" type="date"></div>
          </div>
          <div class="row">
            <button class="btn secondary" @click="showContract = false">Cancel</button>
            <button class="btn primary" :disabled="submitting" @click="saveContract">Save Contract</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
