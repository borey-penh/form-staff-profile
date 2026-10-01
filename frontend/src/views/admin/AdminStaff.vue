<script setup>
import { onMounted, ref } from 'vue'
import { getAdminStaff, createStaff } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const toast = useToastStore()
const staff = ref([])
const search = ref('')
const loading = ref(true)
const showAdd = ref(false)
const submitting = ref(false)

const form = ref({
  staffId: '', firstName: '', lastName: '', email: '',
  role: 'staff', position: '', departmentId: '',
})

async function load() {
  loading.value = true
  try {
    staff.value = (await getAdminStaff(search.value || undefined)).data
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function addStaff() {
  submitting.value = true
  try {
    const res = await createStaff({
      ...form.value,
      departmentId: form.value.departmentId ? Number(form.value.departmentId) : null,
    })
    toast.show('✓ ' + res.message)
    showAdd.value = false
    form.value = { staffId: '', firstName: '', lastName: '', email: '', role: 'staff', position: '', departmentId: '' }
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
        <h1>Staff Management</h1>
        <div class="sub">{{ staff.length }} staff member(s)</div>
      </div>
      <div style="display:flex; gap:10px">
        <input v-model="search" placeholder="Search name or staff ID…" style="width:240px" @input="load">
        <button class="btn primary" @click="showAdd = true">+ Add Staff</button>
      </div>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <table v-else class="table">
        <thead><tr><th>Staff ID</th><th>Name</th><th>Position</th><th>Department</th><th>Role</th><th>Email</th><th></th></tr></thead>
        <tbody>
          <tr v-for="u in staff" :key="u.id">
            <td>{{ u.staffId }}</td>
            <td>
              <strong>{{ u.fullName }}</strong>
              <div v-if="u.nameKh" class="kh">{{ u.nameKh }}</div>
            </td>
            <td>{{ u.position ?? '—' }}</td>
            <td>{{ u.department ?? '—' }}</td>
            <td><span class="badge" :class="u.role === 'admin' ? 'info' : 'neutral'">{{ u.role }}</span></td>
            <td>{{ u.email }}</td>
            <td><RouterLink class="btn sm secondary" :to="'/admin/staff/' + u.id">View</RouterLink></td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Add staff modal -->
    <div class="modal-overlay" :class="{ show: showAdd }">
      <div class="modal" v-if="showAdd" style="max-width:520px">
        <h3>Add Staff</h3>
        <p>Default password: <code>password</code></p>

        <div class="row2">
          <div class="field"><label>Staff ID <span class="req">*</span></label><input v-model="form.staffId" placeholder="ST-00124"></div>
          <div class="field">
            <label>Role</label>
            <select v-model="form.role"><option value="staff">Staff</option><option value="admin">Admin / HR</option></select>
          </div>
        </div>
        <div class="row2">
          <div class="field"><label>First Name <span class="req">*</span></label><input v-model="form.firstName"></div>
          <div class="field"><label>Last Name <span class="req">*</span></label><input v-model="form.lastName"></div>
        </div>
        <div class="field"><label>Email <span class="req">*</span></label><input v-model="form.email" type="email"></div>
        <div class="row2">
          <div class="field"><label>Position</label><input v-model="form.position"></div>
          <div class="field"><label>Department ID</label><input v-model="form.departmentId" type="number" placeholder="1"></div>
        </div>

        <div class="row">
          <button class="btn secondary" @click="showAdd = false">Cancel</button>
          <button class="btn primary" :disabled="submitting" @click="addStaff">Create Staff</button>
        </div>
      </div>
    </div>
  </div>
</template>
