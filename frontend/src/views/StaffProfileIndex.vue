<script setup>
import { onMounted } from 'vue'
import { useStaffProfilesStore } from '@/stores/staffProfiles'
import { formatDate } from '@/utils/format'

const store = useStaffProfilesStore()

function go(page) {
  if (page >= 1 && page <= store.lastPage) store.fetch(page)
}

onMounted(() => store.fetch())
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Submitted Profiles</h1>
        <div class="sub">{{ store.total }} profile(s) on record</div>
      </div>
      <RouterLink class="btn primary" to="/staff-profiles/new">+ New Profile</RouterLink>
    </div>

    <section class="card">
      <div v-if="store.loading && store.items.length === 0" class="empty-note">Loading…</div>

      <div v-else-if="!store.hasItems" class="empty-note">
        No profiles submitted yet.
        <RouterLink to="/staff-profiles/new">Create the first one</RouterLink>.
      </div>

      <table v-else class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Department Contact</th>
            <th>Submitted</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in store.items" :key="p.id">
            <td>{{ p.id }}</td>
            <td>
              <strong>{{ p.nameEn }}</strong>
              <div v-if="p.nameKh" class="kh">{{ p.nameKh }}</div>
            </td>
            <td><span class="badge">{{ p.gender }}</span></td>
            <td>{{ p.email }}<div class="help">{{ p.phone }}</div></td>
            <td>{{ formatDate(p.createdAt) }}</td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.lastPage > 1" style="display:flex; gap:8px; margin-top:16px; align-items:center;">
        <button class="btn secondary" :disabled="store.currentPage <= 1" @click="go(store.currentPage - 1)">← Prev</button>
        <span class="help">Page {{ store.currentPage }} of {{ store.lastPage }}</span>
        <button class="btn secondary" :disabled="store.currentPage >= store.lastPage" @click="go(store.currentPage + 1)">Next →</button>
      </div>
    </section>
  </div>
</template>
