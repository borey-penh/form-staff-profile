<script setup>
import { computed, onMounted, ref } from 'vue'
import { getAdminCompliances, createCompliance, updateCompliance, deleteCompliance } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const toast = useToastStore()
const items = ref([])
const loading = ref(true)
const showAdd = ref(false)
const submitting = ref(false)
const title = ref('')
const description = ref('')
const pendingDelete = ref(null)

const search = ref('')

async function load() {
  loading.value = true
  try {
    items.value = (await getAdminCompliances()).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

const totalSignatures = computed(() => items.value.reduce((s, c) => s + c.signatures, 0))
const activeCount = computed(() => items.value.filter((c) => c.active).length)

const filtered = computed(() =>
  items.value.filter((c) =>
    !search.value || `${c.title} ${c.description ?? ''}`.toLowerCase().includes(search.value.toLowerCase())
  )
)

async function add() {
  if (!title.value.trim()) return toast.show('Title is required.')
  submitting.value = true
  try {
    await createCompliance({ title: title.value, description: description.value })
    toast.show(`✓ Compliance "${title.value}" created — instantly visible to all staff.`)
    title.value = ''
    description.value = ''
    showAdd.value = false
    await load()
  } catch (e) {
    toast.show(e.message)
  } finally {
    submitting.value = false
  }
}

async function toggleActive(c) {
  await updateCompliance(c.id, { active: !c.active })
  await load()
}

async function remove() {
  const c = pendingDelete.value
  pendingDelete.value = null
  if (!c) return
  await deleteCompliance(c.id)
  toast.show('Compliance deleted.')
  await load()
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Compliance Management</h1>
        <div class="sub">Create compliance items once — staff sign them without any new development.</div>
      </div>
      <button class="btn primary" @click="showAdd = true">+ Create Compliance</button>
    </div>

    <!-- Stats -->
    <div class="grid4">
      <div class="stat-card"><div class="label">Total Policies</div><div class="value">{{ items.length }}</div></div>
      <div class="stat-card"><div class="label">Active</div><div class="value">{{ activeCount }}</div></div>
      <div class="stat-card"><div class="label">Inactive</div><div class="value">{{ items.length - activeCount }}</div></div>
      <div class="stat-card"><div class="label">Total Signatures</div><div class="value">{{ totalSignatures }}</div></div>
    </div>

    <section class="card">
      <div class="card-head">
        <h2>All Compliance Items</h2>
        <div class="spacer"></div>
        <div class="search" style="max-width:240px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          <input v-model="search" placeholder="Search policies...">
        </div>
      </div>

      <LoadingState v-if="loading" />

      <div v-else-if="filtered.length" class="table-wrap">
        <table class="table">
          <thead><tr><th>Compliance</th><th>Signatures</th><th>Status</th><th style="width:220px"></th></tr></thead>
          <tbody>
            <tr v-for="c in filtered" :key="c.id">
              <td>
                <strong>{{ c.title }}</strong>
                <div class="help" v-if="c.description">{{ c.description.slice(0, 80) }}{{ c.description.length > 80 ? '…' : '' }}</div>
              </td>
              <td><span class="badge info">{{ c.signatures }}</span></td>
              <td><span class="badge" :class="c.active ? 'approved' : 'neutral'">{{ c.active ? 'Active' : 'Inactive' }}</span></td>
              <td>
                <div class="actions-cell">
                  <button class="btn sm secondary" @click="toggleActive(c)">{{ c.active ? 'Deactivate' : 'Activate' }}</button>
                  <button class="btn sm danger" @click="pendingDelete = c">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState v-else title="No compliance items" desc="Create your first policy for staff to sign." />
    </section>

    <!-- Create modal -->
    <div class="modal-overlay" :class="{ show: showAdd }">
      <div class="modal" v-if="showAdd">
        <h3>New Compliance Item</h3>
        <div class="field"><label>Title <span class="req">*</span></label><input v-model="title" placeholder="e.g. Environmental Safeguarding"></div>
        <div class="field"><label>Description / Declaration text</label><textarea v-model="description" rows="3"></textarea></div>
        <div class="row">
          <button class="btn secondary" @click="showAdd = false">Cancel</button>
          <button class="btn primary" :disabled="submitting" @click="add">Create</button>
        </div>
      </div>
    </div>

    <!-- Delete confirm -->
    <ConfirmDialog
      v-if="pendingDelete"
      title="Delete compliance?"
      :message="'This will permanently delete \u201C' + pendingDelete.title + '\u201D and remove its signature records for all staff. This cannot be undone.'"
      confirm-text="Delete permanently"
      danger
      @confirm="remove"
      @cancel="pendingDelete = null"
    />
  </div>
</template>
