<script setup>
import { computed, ref } from 'vue'
import { submitRequest } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useRouter } from 'vue-router'

const toast = useToastStore()
const router = useRouter()

const form = ref({ date: '', startTime: '', endTime: '', reason: '', supervisorName: '' })
const submitting = ref(false)

const hours = computed(() => {
  if (!form.value.startTime || !form.value.endTime) return 0
  const [sh, sm] = form.value.startTime.split(':').map(Number)
  const [eh, em] = form.value.endTime.split(':').map(Number)
  let mins = (eh * 60 + em) - (sh * 60 + sm)
  if (mins < 0) mins += 24 * 60
  return Math.round((mins / 60) * 100) / 100
})

async function submit() {
  if (!form.value.date || !form.value.startTime || !form.value.endTime) {
    return toast.show('Fill in date and times.')
  }
  submitting.value = true
  try {
    await submitRequest('Overtime', { ...form.value, hours })
    toast.show('✓ Overtime request submitted — status: Pending')
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
        <h1>Overtime Request</h1>
        <div class="sub">Total hours are calculated automatically.</div>
      </div>
    </div>

    <section class="card" style="max-width:640px">
      <div class="row2">
        <div class="field"><label>Date <span class="req">*</span></label><input v-model="form.date" type="date"></div>
        <div class="field"><label>Supervisor</label><input v-model="form.supervisorName" placeholder="Supervisor name"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Start Time <span class="req">*</span></label><input v-model="form.startTime" type="time"></div>
        <div class="field"><label>End Time <span class="req">*</span></label><input v-model="form.endTime" type="time"></div>
      </div>
      <div class="field">
        <label>Total Hours</label>
        <input :value="hours || '—'" disabled>
        <div class="help">Automatically calculated.</div>
      </div>
      <div class="field"><label>Reason</label><textarea v-model="form.reason" rows="3"></textarea></div>

      <div class="actions">
        <button class="btn primary" :disabled="submitting" @click="submit">Submit Request</button>
      </div>
    </section>
  </div>
</template>
