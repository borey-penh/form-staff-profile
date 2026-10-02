<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRequestEdit } from '@/composables/useRequestEdit'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const { editId, currentAttachment, loadForEdit, save } = useRequestEdit('Travel')

const form = ref({
  purpose: '', destination: '', startDate: '', endDate: '',
  transport: 'Car', accommodation: '',
  costs: { transport: '', accommodation: '', meals: '', other: '' },
})
const submitting = ref(false)
const attachment = ref(null)
const fileInput = ref(null)

function clearAttachment() {
  attachment.value = null
  if (fileInput.value) fileInput.value.value = ''
}

onMounted(() => loadForEdit((f) => {
  form.value.purpose = f.purpose ?? ''
  form.value.destination = f.destination ?? ''
  form.value.startDate = f.startDate ?? ''
  form.value.endDate = f.endDate ?? ''
  if (f.transport) form.value.transport = f.transport
  form.value.accommodation = f.accommodation ?? ''
  if (f.costs) {
    for (const k of Object.keys(form.value.costs)) {
      form.value.costs[k] = f.costs[k] ?? ''
    }
  }
}))

const total = computed(() =>
  Object.values(form.value.costs).reduce((s, v) => s + (parseFloat(v) || 0), 0)
)

async function submit() {
  submitting.value = true
  try {
    await save({
      ...form.value,
      costs: Object.fromEntries(Object.entries(form.value.costs).map(([k, v]) => [k, parseFloat(v) || 0])),
    }, attachment.value)
    toast.show(editId.value ? '✓ Travel application updated' : '✓ Travel application submitted — status: Pending')
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

    <section class="card" style="max-width:960px">
      <div class="card-head"><h2>Travel Information</h2>
        <span v-if="editId" class="help">✏️ Editing request #{{ editId }} — it stays editable until someone approves or rejects it.</span>
      </div>

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
        <div class="radio-pills">
          <label v-for="t in ['Car','Motorbike','Bus','Airplane','Other']" :key="t" :class="{ on: form.transport === t }">
            <input type="radio" :value="t" v-model="form.transport"> {{ t }}
          </label>
        </div>
      </div>

      <div class="card-head" style="margin-top:10px"><h2>Estimated Cost</h2></div>
      <div class="row2">
        <div class="field">
          <label>Transport</label>
          <div class="money-wrap"><span class="cur">$</span><input v-model="form.costs.transport" type="number" min="0" step="0.01" placeholder="0.00"></div>
        </div>
        <div class="field">
          <label>Accommodation</label>
          <div class="money-wrap"><span class="cur">$</span><input v-model="form.costs.accommodation" type="number" min="0" step="0.01" placeholder="0.00"></div>
        </div>
      </div>
      <div class="row2">
        <div class="field">
          <label>Meals</label>
          <div class="money-wrap"><span class="cur">$</span><input v-model="form.costs.meals" type="number" min="0" step="0.01" placeholder="0.00"></div>
        </div>
        <div class="field">
          <label>Other</label>
          <div class="money-wrap"><span class="cur">$</span><input v-model="form.costs.other" type="number" min="0" step="0.01" placeholder="0.00"></div>
        </div>
      </div>
      <div class="total-bar">
        <span>Total Estimated Cost</span>
        <strong>${{ total.toFixed(2) }}</strong>
      </div>

      <div class="field" style="margin-top:14px">
        <label>Attachment (optional — PDF/Image, max 5MB)</label>
        <div class="upload-box" style="margin:0">
          <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" @change="e => attachment = e.target.files[0]">
          <button v-if="attachment" class="btn sm secondary" type="button" @click="clearAttachment">✕ Remove</button>
        </div>
        <div v-if="attachment" class="help" style="margin-top:4px">📎 {{ attachment.name }}</div>
        <div v-else-if="currentAttachment" class="help" style="margin-top:4px">
          📎 <a :href="currentAttachment" target="_blank" rel="noopener">Current attachment</a> kept — choose a file to replace it.
        </div>
      </div>

      <div class="actions">
        <span class="help" style="margin-right:auto">Dates and costs are estimates — finance reconciles after the trip.</span>
        <button class="btn secondary" @click="$router.back()">Cancel</button>
        <button class="btn primary" :disabled="submitting" @click="submit">
          <span v-if="submitting">Saving…</span>
          <span v-else>{{ editId ? '✓ Update Application' : '✓ Submit Application' }}</span>
        </button>
      </div>
    </section>
  </div>
</template>
