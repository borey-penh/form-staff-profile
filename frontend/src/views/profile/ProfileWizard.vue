<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import {
  getProfile, savePersonal, saveQualifications, saveFamily,
  uploadDocument, deleteDocument, submitDeclaration,
} from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import SignaturePad from '@/components/SignaturePad.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const toast = useToastStore()
const step = ref(1)
const loading = ref(true)
const saving = ref(false)
const errors = ref({})

const user = reactive({})
const quals = ref([])
const spouse = ref({ name: '', occupation: '', phone: '' })
const children = ref([])
const emergency = ref([{ name: '', relationship: '', phone: '', addr: '' }])
const documents = ref([])
const signature = ref(null)

const steps = ['Personal Information', 'Professional Qualifications', 'Family Information', 'Supporting Documents', 'Declaration']

const sIcons = {
  cap: '<path d="M2 9l10-4 10 4-10 4z"/><path d="M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/><path d="M22 9v5"/>',
  briefcase: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>',
  heart: '<path d="M19.5 13.5 12 21l-7.5-7.5a5 5 0 1 1 7.5-6.5 5 5 0 1 1 7.5 6.5z"/>',
  smile: '<circle cx="12" cy="12" r="9"/><path d="M8.5 14a4.5 4.5 0 0 0 7 0"/><path d="M9 9.5h.01M15 9.5h.01"/>',
  phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.4-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.6 2z"/>',
  file: '<path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7z"/><path d="M14 3v4h4"/>',
  upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>',
}

const docTypes = ['National ID', 'CV', 'Degree Certificate', 'Contract', 'Other']
const newDocType = ref('National ID')
const newDocFile = ref(null)

const blankQual = (type) => ({
  type, title: '', institution: '', field: '',
  startDate: '', endDate: '', description: '',
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const d = await getProfile()
    Object.assign(user, d.user)
    quals.value = d.qualifications ?? []
    spouse.value = d.spouse ?? { name: '', occupation: '', phone: '' }
    children.value = d.children ?? []
    emergency.value = d.emergencyContacts?.length ? d.emergencyContacts : [{ name: '', relationship: '', phone: '', addr: '' }]
    documents.value = d.documents ?? []
  } finally {
    loading.value = false
  }
}

const genderOptions = ['Male', 'Female', 'Other']
const maritalOptions = ['Single', 'Married', 'Divorced', 'Widowed']

async function saveStep1() {
  errors.value = {}
  saving.value = true
  try {
    await savePersonal({
      firstName: user.firstName, lastName: user.lastName, nameKh: user.nameKh,
      dob: user.dob, gender: user.gender, pob: user.pob, nationality: user.nationality,
      nid: user.nid, marital: user.marital, phone: user.phone, email: user.email,
      address: user.address,
    })
    toast.show('✓ Personal information saved')
    step.value = 2
  } catch (e) {
    errors.value = e.errors ?? {}
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

async function saveStep2() {
  saving.value = true
  try {
    await saveQualifications(quals.value)
    toast.show('✓ Qualifications saved')
    step.value = 3
  } catch (e) {
    toast.show(e.message)
  } finally {
    saving.value = false
  }
}

async function saveStep3() {
  errors.value = {}
  saving.value = true
  try {
    await saveFamily({
      spouse: spouse.value.name ? spouse.value : null,
      children: children.value,
      emergencyContacts: emergency.value,
    })
    toast.show('✓ Family information saved')
    step.value = 4
  } catch (e) {
    errors.value = e.errors ?? {}
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

async function upload() {
  if (!newDocFile.value) {
    toast.show('Choose a file first.')
    return
  }
  const fd = new FormData()
  fd.append('type', newDocType.value)
  fd.append('file', newDocFile.value)
  try {
    const res = await uploadDocument(fd)
    documents.value.push(res.document)
    newDocFile.value = null
    toast.show('✓ Document uploaded — pending verification')
  } catch (e) {
    toast.show(e.message)
  }
}

async function removeDoc(id) {
  await deleteDocument(id)
  documents.value = documents.value.filter((d) => d.id !== id)
}

async function declare() {
  if (!signature.value) {
    toast.show('Please draw your signature.')
    return
  }
  saving.value = true
  try {
    await submitDeclaration(signature.value)
    toast.show('✓ Declaration submitted. Profile complete!')
    signature.value = null
  } catch (e) {
    toast.show(e.message)
  } finally {
    saving.value = false
  }
}

const education = computed(() => quals.value.filter((q) => q.type === 'education'))
const experience = computed(() => quals.value.filter((q) => q.type === 'experience'))
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Personnel Profile</h1>
        <div class="sub">Complete all five sections — your progress is saved per step.</div>
      </div>
    </div>

    <!-- Stepper -->
    <div class="wizard-steps">
      <button v-for="(s, i) in steps" :key="s" class="wstep"
              :class="{ active: step === i + 1, done: step > i + 1 }"
              @click="step = i + 1">
        <span class="bubble">
          <svg v-if="step > i + 1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.check"></svg>
          <template v-else>{{ i + 1 }}</template>
        </span>
        <span class="wlabel">{{ s }}</span>
      </button>
    </div>

    <LoadingState v-if="loading" />

    <template v-else>
      <!-- STEP 1 -->
      <section v-show="step === 1" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.user"></svg></span>
          Personal Details
        </div>

        <div class="row2">
          <div class="field">
            <label>First Name (English) <span class="req">*</span></label>
            <input v-model="user.firstName" :class="{ invalid: errors.firstName }" placeholder="e.g. Borey">
          </div>
          <div class="field">
            <label>Last Name (English) <span class="req">*</span></label>
            <input v-model="user.lastName" :class="{ invalid: errors.lastName }" placeholder="e.g. Penh">
          </div>
        </div>
        <div class="field">
          <label>Name in Khmer <span style="color:var(--muted); font-weight:400">(ឈ្មោះជាអក្សរខ្មែរ)</span></label>
          <input v-model="user.nameKh" class="kh" placeholder="សូមបំពេញឈ្មោះជាអក្សរខ្មែរ">
        </div>
        <div class="row2">
          <div class="field">
            <label>Sex <span class="req">*</span></label>
            <select v-model="user.gender"><option v-for="g in genderOptions" :key="g">{{ g }}</option></select>
          </div>
          <div class="field">
            <label>Date of Birth <span class="req">*</span></label>
            <input v-model="user.dob" type="date">
          </div>
        </div>
        <div class="row2">
          <div class="field">
            <label>Place of Birth <span class="req">*</span></label>
            <input v-model="user.pob" placeholder="City / Province">
          </div>
          <div class="field">
            <label>Nationality <span class="req">*</span></label>
            <input v-model="user.nationality" placeholder="e.g. Khmer">
          </div>
        </div>
        <div class="row2">
          <div class="field">
            <label>ID / Passport Number <span class="req">*</span></label>
            <input v-model="user.nid">
          </div>
          <div class="field">
            <label>Marital Status <span class="req">*</span></label>
            <select v-model="user.marital"><option v-for="m in maritalOptions" :key="m">{{ m }}</option></select>
          </div>
        </div>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.phone"></svg></span>
          Contact Information
        </div>

        <div class="row2">
          <div class="field">
            <label>Phone Number <span class="req">*</span></label>
            <input v-model="user.phone" type="tel" placeholder="e.g. 012 345 678">
          </div>
          <div class="field">
            <label>Email <span class="req">*</span></label>
            <input v-model="user.email" type="email" placeholder="e.g. name@example.com">
          </div>
        </div>
        <div class="field">
          <label>Current Address <span class="req">*</span></label>
          <textarea v-model="user.address" rows="2" placeholder="House number, street, village, commune, district, province"></textarea>
        </div>

        <div class="actions">
          <span class="help" style="margin-right:auto">Step {{ step }} of 5</span>
          <button class="btn primary" :disabled="saving" @click="saveStep1">Save &amp; Continue →</button>
        </div>
      </section>

      <!-- STEP 2 -->
      <section v-show="step === 2" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.cap"></svg></span>
          Education
          <span class="count">{{ education.length }}</span>
        </div>

        <div v-for="(q, i) in education" :key="'e' + i" class="item-row">
          <div class="grow">
            <div class="row3">
              <div class="field"><label>Qualification</label>
                <select v-model="q.title"><option>Bachelor Degree</option><option>Master Degree</option><option>Doctorate</option><option>Diploma</option><option>High School</option><option>Vocational</option></select>
              </div>
              <div class="field"><label>Institution</label><input v-model="q.institution"></div>
              <div class="field"><label>Field of Study</label><input v-model="q.field"></div>
            </div>
            <div class="row2">
              <div class="field"><label>Start Date</label><input v-model="q.startDate" type="date"></div>
              <div class="field"><label>End Date</label><input v-model="q.endDate" type="date"></div>
            </div>
          </div>
          <button class="remove-btn" aria-label="Remove" @click="quals = quals.filter(x => x !== q)">✕</button>
        </div>
        <button class="add-btn" style="margin-bottom:22px" @click="quals.push(blankQual('education'))">+ Add Qualification</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.briefcase"></svg></span>
          Professional Experience
          <span class="count">{{ experience.length }}</span>
        </div>

        <div v-for="(q, i) in experience" :key="'x' + i" class="item-row">
          <div class="grow">
            <div class="row2">
              <div class="field"><label>Position</label><input v-model="q.title"></div>
              <div class="field"><label>Organization</label><input v-model="q.institution"></div>
            </div>
            <div class="row2">
              <div class="field"><label>Start Date</label><input v-model="q.startDate" type="date"></div>
              <div class="field"><label>End Date</label><input v-model="q.endDate" type="date"></div>
            </div>
            <div class="field"><label>Responsibilities</label><textarea v-model="q.description" rows="2"></textarea></div>
          </div>
          <button class="remove-btn" aria-label="Remove" @click="quals = quals.filter(x => x !== q)">✕</button>
        </div>
        <button class="add-btn" @click="quals.push(blankQual('experience'))">+ Add Experience</button>

        <div class="actions">
          <button class="btn secondary" @click="step = 1">← Back</button>
          <button class="btn primary" :disabled="saving" @click="saveStep2">Save &amp; Continue →</button>
        </div>
      </section>

      <!-- STEP 3 -->
      <section v-show="step === 3" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.heart"></svg></span>
          Spouse
        </div>

        <div class="row3">
          <div class="field"><label>Name</label><input v-model="spouse.name"></div>
          <div class="field"><label>Occupation</label><input v-model="spouse.occupation"></div>
          <div class="field"><label>Phone</label><input v-model="spouse.phone" type="tel"></div>
        </div>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.smile"></svg></span>
          Children
          <span class="count">{{ children.length }}</span>
        </div>

        <div v-for="(c, i) in children" :key="i" class="item-row">
          <div class="grow row3" style="align-items:end">
            <div class="field"><label>Name</label><input v-model="c.name"></div>
            <div class="field"><label>Date of Birth</label><input v-model="c.dob" type="date"></div>
            <div class="field"><label>Sex</label>
              <select v-model="c.gender"><option>Male</option><option>Female</option><option>Other</option></select>
            </div>
          </div>
          <button class="remove-btn" aria-label="Remove" @click="children.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" style="margin-bottom:22px" @click="children.push({ name: '', dob: '', gender: 'Male', status: 'Alive' })">+ Add Child</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.phone"></svg></span>
          Emergency Family Contact
        </div>

        <div v-for="(e, i) in emergency" :key="'ec' + i" class="item-row">
          <div class="grow">
            <div class="row3">
              <div class="field"><label>Name <span class="req">*</span></label><input v-model="e.name"></div>
              <div class="field"><label>Relationship <span class="req">*</span></label><input v-model="e.relationship" placeholder="e.g. Father, Spouse"></div>
              <div class="field"><label>Phone <span class="req">*</span></label><input v-model="e.phone" type="tel"></div>
            </div>
          </div>
          <button v-if="emergency.length > 1" class="remove-btn" aria-label="Remove" @click="emergency.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" @click="emergency.push({ name: '', relationship: '', phone: '', addr: '' })">+ Add Contact</button>

        <div class="actions">
          <button class="btn secondary" @click="step = 2">← Back</button>
          <button class="btn primary" :disabled="saving" @click="saveStep3">Save &amp; Continue →</button>
        </div>
      </section>

      <!-- STEP 4 -->
      <section v-show="step === 4" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.file"></svg></span>
          Supporting Documents
        </div>
        <div class="card-desc" style="margin-top:-6px">Upload official documents. HR will verify each submission.</div>

        <div v-if="documents.length" class="table-wrap" style="margin-bottom:18px">
          <table class="table">
            <thead><tr><th>Document</th><th>File</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <tr v-for="d in documents" :key="d.id">
                <td>{{ d.type }}</td>
                <td><a :href="d.url" target="_blank">{{ d.originalName }}</a></td>
                <td><StatusBadge :status="d.status" /></td>
                <td><button class="remove-btn" aria-label="Delete" @click="removeDoc(d.id)">✕</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState
          v-else
          title="No documents uploaded yet"
          desc="Add your ID, CV or certificates below."
          style="padding:26px 10px"
        />

        <div class="upload-box">
          <div class="row3" style="flex:1; align-items:end; gap:12px; margin:0">
            <div class="field" style="margin:0">
              <label>Document Type</label>
              <select v-model="newDocType"><option v-for="t in docTypes" :key="t">{{ t }}</option></select>
            </div>
            <div class="field" style="margin:0">
              <label>File (PDF/Image, max 10MB)</label>
              <input type="file" @change="e => newDocFile = e.target.files[0]">
            </div>
          </div>
          <button class="btn primary" @click="upload">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.upload"></svg>
            Upload
          </button>
        </div>

        <div class="actions">
          <button class="btn secondary" @click="step = 3">← Back</button>
          <button class="btn primary" @click="step = 5">Continue →</button>
        </div>
      </section>

      <!-- STEP 5 -->
      <section v-show="step === 5" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.pen"></svg></span>
          Declaration
        </div>

        <div class="decl-list">
          <div class="decl-item">
            <span class="check-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.check"></svg></span>
            <span>I confirm that the information provided in my personnel profile is correct and complete.</span>
          </div>
          <div class="decl-item">
            <span class="check-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.check"></svg></span>
            <span>I agree to inform HR if my information changes.</span>
          </div>
        </div>

        <div class="field">
          <label>Signature <span class="req">*</span></label>
          <SignaturePad v-model="signature" />
          <div class="help">Draw your signature above, then submit.</div>
        </div>

        <div class="actions">
          <button class="btn secondary" @click="step = 4">← Back</button>
          <button class="btn primary" :disabled="saving" @click="declare">✓ Submit Declaration</button>
        </div>
      </section>
    </template>
  </div>
</template>
