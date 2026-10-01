<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getCompliances, signCompliance } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import SignaturePad from '@/components/SignaturePad.vue'
import LoadingState from '@/components/LoadingState.vue'
import { categoryOf, statusOf } from '@/utils/compliance'
import { todayISO, formatDate } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()

const items = ref([])
const item = ref(null)
const loading = ref(true)
const submitting = ref(false)
const submitted = ref(false)

const confirm1 = ref(false)
const confirm2 = ref(false)
const signature = ref(null)

onMounted(async () => {
  try {
    items.value = (await getCompliances()).data
    item.value = items.value.find((c) => String(c.id) === route.params.id) ?? null
  } finally {
    loading.value = false
  }
})

const category = computed(() => categoryOf(item.value?.title ?? ''))
const status = computed(() => (item.value ? statusOf(item.value) : ''))

const canSubmit = computed(() =>
  item.value && !item.value.signed && confirm1.value && confirm2.value && signature.value
)

async function submit() {
  if (!canSubmit.value) {
    toast.show('Please tick both confirmations and draw your signature.')
    return
  }
  submitting.value = true
  try {
    await signCompliance(item.value.id, signature.value)
    submitted.value = true
  } catch (e) {
    toast.show(e.message)
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push('/compliances')
}
</script>

<template>
  <div>
    <!-- Breadcrumb -->
    <div class="crumbs">
      <RouterLink to="/compliances">Compliances</RouterLink>
      <span class="sep">/</span>
      <span class="current">{{ item?.title ?? '…' }}</span>
    </div>

    <LoadingState v-if="loading" />

    <template v-else>
      <!-- Success screen -->
      <section v-if="submitted" class="card success-card">
        <div class="success-ico">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h2>Declaration Submitted</h2>
        <p class="success-sub">{{ item.title }}</p>
        <p>Your declaration has been submitted successfully.</p>
        <div class="success-meta">
          <div><span>Status</span><strong>Under Review</strong></div>
          <div><span>Submitted</span><strong>{{ formatDate(todayISO()) }}</strong></div>
          <div><span>Signed by</span><strong>{{ auth.fullName }}</strong></div>
        </div>
        <button class="btn primary" @click="goBack">Back to Compliances</button>
      </section>

      <!-- Not found -->
      <section v-else-if="!item" class="card">
        <p class="help">This compliance item doesn't exist or is no longer assigned.</p>
        <button class="btn secondary" @click="goBack">← Back to Compliances</button>
      </section>

      <!-- Detail -->
      <template v-else>
        <section class="card detail-card">
          <div class="detail-head">
            <span class="comp-ico" :style="{ background: category.soft, color: category.color }">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="category.icon"></svg>
            </span>
            <div style="flex:1; min-width:0">
              <h1>{{ item.title }}</h1>
              <p class="comp-cat" :style="{ color: category.color }">{{ category.key }}</p>
            </div>
            <span class="badge" :class="item.signed ? 'approved' : 'pending'">{{ status }}</span>
          </div>

          <div class="meta-grid">
            <div class="meta-box">
              <div class="label">Due Date</div>
              <div class="v">—</div>
              <div class="help">Set by HR (not configured)</div>
            </div>
            <div class="meta-box">
              <div class="label">Category</div>
              <div class="v">{{ category.key }}</div>
            </div>
            <div class="meta-box">
              <div class="label">Assigned By</div>
              <div class="v">HR</div>
            </div>
            <div class="meta-box">
              <div class="label">Version</div>
              <div class="v">v1.0</div>
            </div>
          </div>

          <div class="detail-section">
            <h3>Description</h3>
            <p>{{ item.description || 'No description provided.' }}</p>
          </div>

          <div class="detail-section">
            <h3>Policy Document</h3>
            <div v-if="item.policyUrl" class="policy-row">
              <span class="policy-ico">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7z"/><path d="M14 3v4h4"/></svg>
              </span>
              <div style="flex:1">
                <strong style="font-size:13px">Policy Document</strong>
                <div class="help">PDF document</div>
              </div>
              <a class="btn sm secondary" :href="item.policyUrl" target="_blank">View Document</a>
              <a class="btn sm secondary" :href="item.policyUrl" download>Download</a>
            </div>
            <p v-else class="help">No policy document attached.</p>
          </div>

          <template v-if="!item.signed">
            <div class="detail-section">
              <h3>Declaration</h3>
              <label class="decl-check">
                <input v-model="confirm1" type="checkbox">
                <span>I confirm that I have read and understood the {{ item.title }} policy.</span>
              </label>
              <label class="decl-check">
                <input v-model="confirm2" type="checkbox">
                <span>I agree to follow the requirements of this policy.</span>
              </label>
            </div>

            <div class="detail-section">
              <h3>Digital Signature</h3>
              <SignaturePad v-model="signature" />
              <div class="sig-meta">
                <div class="field" style="margin:0">
                  <label>Date</label>
                  <input :value="todayISO()" disabled>
                </div>
                <div class="field" style="margin:0">
                  <label>Name</label>
                  <input :value="auth.fullName" disabled>
                </div>
              </div>
            </div>

            <div class="actions">
              <button class="btn secondary" @click="goBack">Back</button>
              <button class="btn primary" :disabled="!canSubmit || submitting" @click="submit">
                {{ submitting ? 'Submitting…' : 'Submit Declaration' }}
              </button>
            </div>
          </template>

          <template v-else>
            <div class="signed-banner">
              <span class="success-ico small">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              </span>
              <div>
                <strong>You have signed this declaration.</strong>
                <div class="help">Your signature is on file with HR. Thank you.</div>
              </div>
            </div>
            <div class="actions">
              <button class="btn secondary" @click="goBack">← Back to Compliances</button>
            </div>
          </template>
        </section>
      </template>
    </template>
  </div>
</template>
