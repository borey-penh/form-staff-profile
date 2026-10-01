<script setup>
import { computed, onMounted, ref } from 'vue'
import { getCompliances } from '@/services/portalService'
import { useRouter } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import { categoryOf, statusOf, summarize } from '@/utils/compliance'
import { formatDate } from '@/utils/format'

const router = useRouter()
const items = ref([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const categoryFilter = ref('')

async function load() {
  loading.value = true
  try {
    items.value = (await getCompliances()).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

const enriched = computed(() =>
  items.value.map((i) => ({ ...i, category: categoryOf(i.title), status: statusOf(i) }))
)

const stats = computed(() => summarize(items.value))

const categories = computed(() => [...new Set(enriched.value.map((i) => i.category.key))])

const filtered = computed(() =>
  enriched.value.filter((i) => {
    if (search.value && !`${i.title} ${i.description ?? ''}`.toLowerCase().includes(search.value.toLowerCase())) return false
    if (statusFilter.value && i.status !== statusFilter.value) return false
    if (categoryFilter.value && i.category.key !== categoryFilter.value) return false
    return true
  })
)

const required = computed(() => filtered.value.filter((i) => !i.signed))
const done = computed(() => filtered.value.filter((i) => i.signed))

function openDetail(c) {
  router.push(`/compliances/${c.id}`)
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Compliances</h1>
        <div class="sub">Manage your required policies and declarations.</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="card toolbar-card">
      <div class="filters">
        <div class="search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          <input v-model="search" placeholder="Search compliance...">
        </div>
        <select v-model="statusFilter" style="width:auto">
          <option value="">All statuses</option>
          <option>Pending</option>
          <option>Completed</option>
        </select>
        <select v-model="categoryFilter" style="width:auto">
          <option value="">All categories</option>
          <option v-for="c in categories" :key="c">{{ c }}</option>
        </select>
      </div>

      <!-- Stat chips -->
      <div class="comp-stats">
        <div class="cs-card">
          <div class="cs-label">Required</div>
          <div class="cs-value">{{ stats.total }}</div>
          <div class="cs-note">compliance</div>
        </div>
        <div class="cs-card ok">
          <div class="cs-label">Completed</div>
          <div class="cs-value">{{ stats.completed }}</div>
          <div class="cs-note">✓ up to date</div>
        </div>
        <div class="cs-card warn">
          <div class="cs-label">Pending</div>
          <div class="cs-value">{{ stats.pending }}</div>
          <div class="cs-note">⚠ action needed</div>
        </div>
        <div class="cs-card bad">
          <div class="cs-label">Expired</div>
          <div class="cs-value">{{ stats.expired }}</div>
          <div class="cs-note">—</div>
        </div>
      </div>

      <!-- Progress -->
      <div class="comp-progress">
        <div class="cp-head"><span>Compliance Progress</span><strong>{{ stats.progress }}%</strong></div>
        <div class="progress"><div :style="{ width: stats.progress + '%' }"></div></div>
        <div class="help">{{ stats.completed }} of {{ stats.total }} completed</div>
      </div>
    </div>

    <LoadingState v-if="loading" />

    <template v-else>
      <EmptyState
        v-if="!filtered.length"
        title="No compliances found"
        :desc="search || statusFilter || categoryFilter ? 'Try adjusting your search or filters.' : 'Assigned policies will appear here.'"
      />

      <template v-else>
        <!-- Pending -->
        <template v-if="required.length">
          <div class="section-title">Required Compliance</div>
          <div class="comp-grid">
            <div v-for="c in required" :key="c.id" class="comp-card">
              <div class="comp-top">
                <span class="comp-ico" :style="{ background: c.category.soft, color: c.category.color }">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="c.category.icon"></svg>
                </span>
                <span class="badge pending">{{ c.status }}</span>
              </div>
              <h3 class="comp-title">{{ c.title }}</h3>
              <p class="comp-cat" :style="{ color: c.category.color }">{{ c.category.key }}</p>
              <p class="comp-desc">{{ c.description || 'Required for all staff.' }}</p>
              <div class="comp-meta">Assigned by HR</div>
              <div class="comp-actions">
                <button class="btn sm secondary" @click="openDetail(c)">View Details</button>
                <button class="btn sm primary" @click="openDetail(c)">Complete Now →</button>
              </div>
            </div>
          </div>
        </template>

        <!-- Completed -->
        <template v-if="done.length">
          <div class="section-title">Completed</div>
          <div class="comp-grid">
            <div v-for="c in done" :key="c.id" class="comp-card done">
              <div class="comp-top">
                <span class="comp-ico" :style="{ background: c.category.soft, color: c.category.color }">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="c.category.icon"></svg>
                </span>
                <span class="badge approved">Completed</span>
              </div>
              <h3 class="comp-title">{{ c.title }}</h3>
              <p class="comp-cat" :style="{ color: c.category.color }">{{ c.category.key }}</p>
              <p class="comp-desc">{{ c.description || 'Declaration signed.' }}</p>
              <div class="comp-meta">Certificate available in history</div>
              <div class="comp-actions">
                <button class="btn sm secondary" @click="openDetail(c)">View Details</button>
                <span class="signed-mark">✓ Signed</span>
              </div>
            </div>
          </div>
        </template>
      </template>
    </template>
  </div>
</template>
