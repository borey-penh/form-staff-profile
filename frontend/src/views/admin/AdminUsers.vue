<script setup>
import { computed, onMounted, ref } from 'vue'
import { getRoles, getUsers, getUser, updateUser } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'

const toast = useToastStore()
const loading = ref(true)
const users = ref([])
const roles = ref([])
const search = ref('')
const roleFilter = ref('')
const statusFilter = ref('')

const showManage = ref(false)
const saving = ref(false)
const detail = ref(null) // { user, allPermissions, roles }
const selectedExtras = ref([])

const statuses = ['Pending Invitation', 'Active', 'Suspended', 'Inactive']

const filtered = computed(() => {
  let list = users.value
  if (search.value) {
    const s = search.value.toLowerCase()
    list = list.filter((u) =>
      u.fullName?.toLowerCase().includes(s) ||
      u.email?.toLowerCase().includes(s) ||
      u.staffId?.toLowerCase().includes(s))
  }
  if (roleFilter.value) list = list.filter((u) => String(u.roleId) === roleFilter.value)
  if (statusFilter.value) list = list.filter((u) => u.status === statusFilter.value)
  return list
})

async function load() {
  loading.value = true
  try {
    const [u, r] = await Promise.all([getUsers(), getRoles()])
    users.value = u.data
    roles.value = r.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function openManage(u) {
  const res = await getUser(u.id)
  detail.value = res
  selectedExtras.value = res.user.extraPermissions ?? []
  showManage.value = true
}

async function save() {
  saving.value = true
  try {
    await updateUser(detail.value.user.id, {
      roleId: detail.value.user.roleId,
      departmentId: detail.value.user.departmentId,
      position: detail.value.user.position,
      status: detail.value.user.status,
      additionalPermissions: selectedExtras.value,
    })
    toast.show('✓ User saved')
    showManage.value = false
    await load()
  } catch (e) {
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

function toggleExtra(name) {
  const i = selectedExtras.value.indexOf(name)
  i === -1 ? selectedExtras.value.push(name) : selectedExtras.value.splice(i, 1)
}

const groupedPermissions = computed(() => {
  if (!detail.value) return {}
  const groups = {}
  for (const p of detail.value.allPermissions) {
    ;(groups[p.group] ??= []).push(p)
  }
  return groups
})
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>User Management</h1>
        <div class="sub">{{ users.length }} user(s) — roles, status and additional permissions</div>
      </div>
      <input v-model="search" placeholder="Search name, email, staff ID…" style="width:260px">
    </div>

    <div style="display:flex; gap:10px; margin-bottom:14px">
      <select v-model="roleFilter" style="width:auto">
        <option value="">All Roles</option>
        <option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.label }}</option>
      </select>
      <select v-model="statusFilter" style="width:auto">
        <option value="">All Statuses</option>
        <option v-for="s in statuses" :key="s">{{ s }}</option>
      </select>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <table v-else class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Extra Perms</th><th></th></tr></thead>
        <tbody>
          <tr v-for="u in filtered" :key="u.id">
            <td>
              <strong>{{ u.fullName }}</strong>
              <div class="help">{{ u.staffId }}</div>
            </td>
            <td>{{ u.email }}</td>
            <td><span class="badge neutral">{{ u.roleLabel ?? u.role }}</span></td>
            <td><StatusBadge :status="u.status" /></td>
            <td>{{ u.extraPermissionsCount ? `+${u.extraPermissionsCount}` : '—' }}</td>
            <td><button class="btn sm secondary" @click="openManage(u)">Manage</button></td>
          </tr>
          <tr v-if="!filtered.length && !loading">
            <td colspan="6" class="help" style="text-align:center; padding:22px">No users match the filters.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Manage user modal -->
    <div class="modal-overlay" :class="{ show: showManage }">
      <div class="modal" v-if="showManage && detail" style="max-width:640px; max-height:85vh; overflow:auto">
        <h3>User Details</h3>
        <p style="margin-bottom:14px">
          <strong>{{ detail.user.fullName }}</strong> · {{ detail.user.email }}
          <span v-if="detail.user.department" style="color:var(--muted)"> · {{ detail.user.department }}</span>
        </p>

        <div class="row2">
          <div class="field">
            <label>Role</label>
            <select v-model="detail.user.roleId">
              <option v-for="r in detail.roles" :key="r.id" :value="r.id">{{ r.label }}</option>
            </select>
          </div>
          <div class="field">
            <label>Status</label>
            <select v-model="detail.user.status">
              <option v-for="s in statuses" :key="s">{{ s }}</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label>Position</label>
          <input v-model="detail.user.position">
        </div>

        <div class="help" style="text-transform:uppercase; letter-spacing:.06em; margin:12px 0 6px">
          Additional Permissions (on top of role defaults)
        </div>
        <div v-for="(perms, group) in groupedPermissions" :key="group" style="margin-bottom:12px">
          <div class="help" style="margin-bottom:4px">{{ group }}</div>
          <label v-for="p in perms" :key="p.name" style="display:flex; gap:8px; align-items:center; font-size:13.5px; cursor:pointer">
            <input type="checkbox" :checked="selectedExtras.includes(p.name)" @change="toggleExtra(p.name)">
            {{ p.label }}
          </label>
        </div>

        <div class="row">
          <button class="btn secondary" @click="showManage = false">Cancel</button>
          <button class="btn primary" :disabled="saving" @click="save">Save Changes</button>
        </div>
      </div>
    </div>
  </div>
</template>
