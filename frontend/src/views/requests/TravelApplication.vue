<script setup>
import { computed, ref } from 'vue'
import { submitRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const form = ref({
  purpose: '', destination: '', startDate: '', endDate: '',
  transport: 'Car', accommodation: '',
  costs: { transport: 0, accommodation: 0, meals: 0, other: 0 },
})
const submitting = ref(false)

const total = computed(() =>
  Object.values(form.value.costs).reduce((s, v) => s + (parseFloat(v) || 0), 0)
)

async function submit() {
  submitting.value = true
  try {
    await submitRequest('Travel', {
      ...form.value,
      costs: Object.fromEntries(Object.entries(form.value.costs).map(([k, v]) => [k, parseFloat(v) || 0])),
    })
    toast.show('✓ Travel application submitted — status: Pending')
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
        <h1>Travel Application</h1>
        <div class="sub">Request approval for official travel with estimated costs.</div>
      </div>
    </div>

    <section class="card" style="max-width:760px">
      <div class="card-head"><h2>Travel Information</h2></div>

      <div class="field"><label>Purpose of Travel <span class="req">*</span></label><textarea v-model="form.purpose" rows="2"></textarea></div>
      <div class="row2">
        <div class="field"><label>Destination <span class="req">*</span></label><input v-model="form.destination"></div>
        <div class="field"><label>Accommodation</label><input v-model="form.accommodation" placeholder="Hotel / guesthouse"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Start Date <span class="req">*</span></label><input v-model="form.startDate" type="date"></div>
        <div class="field"><label>End Date <span class="req">*</span></label><input v-model="form.endDate" type="date"></div>
      </div>

      <div class="field">
        <label>Transportation <span class="req">*</span></label>
        <div style="display:flex; gap:18px; flex-wrap:wrap">
          <label v-for="t in ['Car','Motorbike','Bus','Airplane','Other']" :key="t" class="checkline" style="margin:0">
            <input type="radio" :value="t" v-model="form.transport"> {{ t }}
          </label>
        </div>
      </div>

      <div class="card-head" style="margin-top:10px"><h2>Estimated Cost</h2></div>
      <div class="row2">
        <div class="field"><label>Transport $</label><input v-model="form.costs.transport" type="number" min="0" step="0.01"></div>
        <div class="field"><label>Accommodation $</label><input v-model="form.costs.accommodation" type="number" min="0" step="0.01"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Meals $</label><input v-model="form.costs.meals" type="number" min="0" step="0.01"></div>
        <div class="field"><label>Other $</label><input v-model="form.costs.other" type="number" min="0" step="0.01"></div>
      </div>
      <div style="display:flex; justify-content:space-between; padding:12px 14px; background:var(--primary-soft); border-radius:10px; margin-bottom:14px">
        <strong>Total</strong><strong>${{ total.toFixed(2) }}</strong>
      </div>

      <div class="actions">
        <button class="btn primary" :disabled="submitting" @click="submit">Submit Application</button>
      </div>
    </section>
  </div>
</template>
