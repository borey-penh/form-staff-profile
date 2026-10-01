<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  getRoles, getRole, createRole, updateRole, deleteRole,
  getInvitations, sendInvitation, resendInvitation, revokeInvitation,
} from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const toast = useToastStore()
const auth = useAuthStore()
const loading = ref(true)
const roles = ref([])
const allPermissions = ref([])
const departments = ref([])
const invitations = ref([])

const showEdit = ref(false)
const editing = ref(null)       // { id, name, label, description, permissions: [] }
const saving = ref(false)
const showCreate = ref(false)
const createForm = ref({ name: '', label: '', description: '' })

const groupedPermissions = computed(() => {
  const groups = {}
  for (const p of allPermissions.value) {
    ;(groups[p.group] ??= []).push(p)
  }
  return groups
})

async function load() {
  loading.value = true
  try {
    const [res, inv] = await Promise.all([getRoles(), getInvitations().catch(() => ({ data: [] }))])
    roles.value = res.data
    allPermissions.value = res.allPermissions
    departments.value = res.departments ?? []
    invitations.value = inv.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function openRole(role) {
  const detail = await getRole(role.id)
  editing.value = {
    id: detail.role.id,
    name: detail.role.name,
    label: detail.role.label,
    description: detail.role.description ?? '',
    permissions: [...detail.role.permissions],
  }
  allPermissions.value = detail.allPermissions
  showEdit.value = true
}

function togglePermission(name) {
  const list = editing.value.permissions
  const i = list.indexOf(name)
  i === -1 ? list.push(name) : list.splice(i, 1)
}

function toggleGroup(group) {
  const names = group.map((p) => p.name)
  const allOn = names.every((n) => editing.value.permissions.includes(n))
  for (const n of names) {
    const i = editing.value.permissions.indexOf(n)
    if (allOn && i !== -1) editing.value.permissions.splice(i, 1)
    else if (!allOn && i === -1) editing.value.permissions.push(n)
  }
}

async function saveRole() {
  saving.value = true
  try {
    await updateRole(editing.value.id, {
      label: editing.value.label,
      description: editing.value.description,
      permissions: editing.value.permissions,
    })
    toast.show('✓ Role saved')
    showEdit.value = false
    await load()
  } catch (e) {
    toast.show(e.message)
  } finally {
    saving.value = false
  }
}

async function addRole() {
  saving.value = true
  try {
    await createRole(createForm.value)
    toast.show('✓ Role created')
    showCreate.value = false
    createForm.value = { name: '', label: '', description: '' }
    await load()
  } catch (e) {
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

async function removeRole(role) {
  if (!confirm(`Delete role "${role.label}"?`)) return
  try {
    await deleteRole(role.id)
    toast.show('Role deleted')
    await load()
  } catch (e) {
    toast.show(e.message)
  }
}

/* ---------------- Invite staff by email ---------------- */
const showInvite = ref(false)
const inviting = ref(false)
const inviteForm = ref({ email: '', firstName: '', lastName: '', departmentId: '', roleId: '', requireProfileSetup: true })
const invitedLink = ref(null)

const pendingInvites = computed(() => invitations.value.filter((i) => i.status === 'Pending First Login' || i.status === 'Expired'))

function openInvite(roleId = '') {
  inviteForm.value = { email: '', firstName: '', lastName: '', departmentId: '', roleId: roleId || roles.value.find((r) => r.name === 'Staff')?.id || '', requireProfileSetup: true }
  invitedLink.value = null
  showInvite.value = true
}

const roleCards = computed(() => roles.value.map((r) => ({
  id: r.id,
  label: r.label,
  desc: r.description,
})))

async function submitInvite() {
  inviting.value = true
  try {
    const res = await sendInvitation({
      ...inviteForm.value,
      roleId: Number(inviteForm.value.roleId),
      departmentId: inviteForm.value.departmentId ? Number(inviteForm.value.departmentId) : null,
    })
    toast.show('✓ ' + res.message)
    invitedLink.value = res.invitationLink
    await load()
  } catch (e) {
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    inviting.value = false
  }
}

async function resend(i) {
  try {
    const res = await resendInvitation(i.id)
    toast.show('✓ ' + res.message)
    await load()
  } catch (e) {
    toast.show(e.message)
  }
}

async function revoke(i) {
  if (!confirm(`Revoke the invitation for ${i.email}?`)) return
  try {
    await revokeInvitation(i.id)
    toast.show('Invitation revoked')
    await load()
  } catch (e) {
    toast.show(e.message)
  }
}

async function copyLink(i) {
  try {
    const res = await resendInvitation(i.id)
    await navigator.clipboard.writeText(res.invitationLink)
    toast.show('✓ Secure link copied — send it to ' + i.email)
  } catch (e) {
    toast.show(e.message)
  }
}

async function copyInvitedLink() {
  try {
    await navigator.clipboard.writeText(invitedLink.value)
    toast.show('✓ Secure link copied')
  } catch {
    toast.show('Select the link text and copy manually.')
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Roles &amp; Permissions</h1>
        <div class="sub">Control what each role can see and do across the portal.</div>
      </div>
      <div style="display:flex; gap:10px">
        <button class="btn primary" @click="openInvite()">+ Invite Staff</button>
        <button class="btn secondary" @click="showCreate = true">Create Role</button>
      </div>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <table v-else class="table">
        <thead><tr><th>Role</th><th>Description</th><th>Users</th><th>Permissions</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in roles" :key="r.id">
            <td><strong>{{ r.label }}</strong></td>
            <td>{{ r.description ?? '—' }}</td>
            <td>{{ r.users }}</td>
            <td><span class="badge info">{{ r.permissions.length }}</span></td>
            <td style="white-space:nowrap">
              <button class="btn sm secondary" @click="openRole(r)">Edit</button>
              <button
                v-if="r.name !== 'Admin'"
                class="btn sm secondary"
                style="margin-left:6px"
                @click="removeRole(r)"
              >Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Pending invitations & staff onboarding -->
    <section class="card" style="margin-top:18px">
      <div class="card-head">
        <h2>Pending Invitations &amp; Staff Onboarding</h2>
        <div class="spacer"></div>
        <span class="badge neutral">{{ pendingInvites.length }} pending</span>
      </div>
      <EmptyState
        v-if="!invitations.length"
        title="No invitations yet"
        desc="Use “+ Invite Staff” to onboard someone by email."
        style="padding:26px 10px"
      />
      <table v-else class="table">
        <thead><tr><th>Invited Email</th><th>Role</th><th>Department</th><th>Invited</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <tr v-for="i in invitations" :key="i.id">
            <td>
              <strong>{{ i.email }}</strong>
              <div v-if="i.fullName" class="help">{{ i.fullName }}</div>
            </td>
            <td>{{ i.roleLabel }}</td>
            <td>{{ i.department ?? '—' }}</td>
            <td>{{ i.invitedAt ? new Date(i.invitedAt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '—' }}</td>
            <td><StatusBadge :status="i.status" /></td>
            <td style="white-space:nowrap">
              <template v-if="i.status === 'Pending First Login' || i.status === 'Expired'">
                <button class="btn sm secondary" @click="resend(i)">Resend</button>
                <button class="btn sm secondary" style="margin-left:6px" title="Copy secure link" @click="copyLink(i)">Copy Link</button>
                <button class="btn sm secondary" style="margin-left:6px; color:var(--red)" @click="revoke(i)">Revoke</button>
              </template>
              <span v-else class="help">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Invite staff modal -->
    <div class="modal-overlay" :class="{ show: showInvite }">
      <div class="modal" v-if="showInvite" style="max-width:560px; max-height:88vh; overflow:auto">
        <h3>Invite Staff Member</h3>
        <p>
          Send an email invitation. The staff member will receive a secure magic link to log in,
          complete their profile, and access portal tools based on the assigned role.
        </p>

        <template v-if="!invitedLink">
          <div class="help" style="background:var(--primary-soft); color:var(--primary); padding:10px 12px; border-radius:10px; margin-bottom:14px; font-weight:600">
            Onboarding flow: ① Email sent → ② Secure link login → ③ Complete profile
          </div>

          <div class="field">
            <label>Staff Email Address <span class="req">*</span></label>
            <input v-model="inviteForm.email" type="email" placeholder="e.g. staff.member@company.com">
          </div>
          <div class="row2">
            <div class="field"><label>First Name</label><input v-model="inviteForm.firstName" placeholder="Optional"></div>
            <div class="field"><label>Last Name</label><input v-model="inviteForm.lastName" placeholder="Optional"></div>
          </div>
          <div class="field">
            <label>Department / Team</label>
            <select v-model="inviteForm.departmentId">
              <option value="">— Assign later —</option>
              <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
            </select>
          </div>

          <div class="field">
            <label>Role Assignment <span class="req">*</span></label>
            <label
              v-for="r in roleCards"
              :key="r.id"
              class="checkline"
              style="border:1px solid var(--border); border-radius:10px; padding:10px 12px; margin-bottom:8px; cursor:pointer"
              :style="Number(inviteForm.roleId) === r.id ? 'border-color:var(--primary); background:var(--primary-soft)' : ''"
            >
              <input type="radio" :value="r.id" v-model.number="inviteForm.roleId">
              <span><strong>{{ r.label }}</strong><br><span class="help" style="margin-top:2px">{{ r.desc }}</span></span>
            </label>
          </div>

          <label class="checkline" style="margin-bottom:16px">
            <input type="checkbox" v-model="inviteForm.requireProfileSetup">
            Require staff to complete profile setup upon first login
          </label>

          <div class="row">
            <button class="btn secondary" @click="showInvite = false">Cancel</button>
            <button class="btn primary" :disabled="inviting || !inviteForm.email || !inviteForm.roleId" @click="submitInvite">
              <span v-if="inviting">Sending…</span>
              <span v-else>Send Invitation &amp; Assign Role</span>
            </button>
          </div>
        </template>

        <template v-else>
          <div class="help" style="background:var(--green-soft); color:var(--green); padding:14px 16px; border-radius:10px; font-weight:600; margin-bottom:14px">
            ✓ Invitation sent. The link expires in 72 hours.
          </div>
          <div class="field">
            <label>Secure magic link (copy &amp; send if email isn't configured)</label>
            <textarea :value="invitedLink" rows="3" readonly @focus="$event.target.select()"></textarea>
          </div>
          <div class="row">
            <button class="btn secondary" @click="copyInvitedLink">Copy Link</button>
            <button class="btn primary" @click="showInvite = false">Done</button>
          </div>
        </template>
      </div>
    </div>

    <!-- Create role modal -->
    <div class="modal-overlay" :class="{ show: showCreate }">
      <div class="modal" v-if="showCreate">
        <h3>Create Role</h3>
        <div class="field"><label>System Name <span class="req">*</span></label><input v-model="createForm.name" placeholder="e.g. Coordinator"></div>
        <div class="field"><label>Display Name <span class="req">*</span></label><input v-model="createForm.label" placeholder="e.g. Coordinator"></div>
        <div class="field"><label>Description</label><textarea v-model="createForm.description" rows="2"></textarea></div>
        <div class="row">
          <button class="btn secondary" @click="showCreate = false">Cancel</button>
          <button class="btn primary" :disabled="saving" @click="addRole">Create</button>
        </div>
      </div>
    </div>

    <!-- Edit role + permissions modal -->
    <div class="modal-overlay" :class="{ show: showEdit }">
      <div class="modal" v-if="showEdit" style="max-width:640px; max-height:85vh; overflow:auto">
        <h3>Edit Role: {{ editing.label }}</h3>

        <div class="field"><label>Display Name</label><input v-model="editing.label"></div>
        <div class="field"><label>Description</label><textarea v-model="editing.description" rows="2"></textarea></div>

        <div class="help" style="margin-bottom:10px">
          Role permissions apply to every user with this role. Grant extras per-user in
          <RouterLink to="/access/users" style="color:var(--primary)">Users</RouterLink>.
        </div>

        <div v-for="(perms, group) in groupedPermissions" :key="group" style="margin-bottom:16px">
          <div class="help" style="text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px">{{ group }}</div>
          <label v-for="p in perms" :key="p.name" style="display:flex; gap:8px; align-items:center; margin-bottom:6px; font-size:13.5px; cursor:pointer">
            <input
              type="checkbox"
              :checked="editing.permissions.includes(p.name)"
              @change="togglePermission(p.name)"
            >
            {{ p.label }}
            <span style="color:var(--muted); font-size:11.5px">{{ p.name }}</span>
          </label>
        </div>

        <div class="row">
          <button class="btn secondary" @click="showEdit = false">Cancel</button>
          <button class="btn primary" :disabled="saving" @click="saveRole">Save Permissions</button>
        </div>
      </div>
    </div>
  </div>
</template>
