<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import {
  getProfile, savePersonal, saveQualifications, saveFamily,
  uploadDocument, deleteDocument, submitDeclaration,
  submitChangeRequest, cancelChangeRequest,
  uploadProfilePhoto, deleteProfilePhoto,
} from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import SignaturePad from '@/components/SignaturePad.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const toast = useToastStore()
const auth = useAuthStore()
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
const lockedFields = ref([])
const changeRequests = ref([])

const canEditAll = computed(() => auth.isAdmin)
const isLocked = (field) => !canEditAll.value && lockedFields.value.includes(field)

const pendingByField = computed(() => {
  const map = {}
  for (const r of changeRequests.value) {
    if (r.status === 'Pending') map[r.field] = r
  }
  return map
})

const fieldLabels = {
  firstName: 'First Name', lastName: 'Last Name', nameKh: 'Name (Khmer)',
  dob: 'Date of Birth', gender: 'Sex', pob: 'Place of Birth',
  nationality: 'Nationality', nid: 'ID / Passport Number', marital: 'Marital Status',
  phone: 'Phone Number', email: 'Email', address: 'Current Address',
}

/* Change-request modal state */
const showCrModal = ref(false)
const crField = ref('')
const crValue = ref('')
const crReason = ref('')
const crSaving = ref(false)
const crErrors = ref({})

function openCrModal(field) {
  crField.value = field
  crValue.value = user[field] ?? ''
  crReason.value = ''
  crErrors.value = {}
  showCrModal.value = true
}

async function sendCr() {
  crSaving.value = true
  crErrors.value = {}
  try {
    await submitChangeRequest(crField.value, crValue.value, crReason.value)
    toast.show('✓ Change request sent to HR for review')
    showCrModal.value = false
    await load()
  } catch (e) {
    crErrors.value = e.errors ?? {}
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    crSaving.value = false
  }
}

async function withdrawCr(id) {
  try {
    await cancelChangeRequest(id)
    toast.show('Change request withdrawn')
    await load()
  } catch (e) {
    toast.show(e.message)
  }
}

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

/* ---- Profile photo (avatar) ---- */
const photoInput = ref(null)
const photoUploading = ref(false)

const initials = computed(() =>
  ((user.firstName?.[0] ?? '') + (user.lastName?.[0] ?? '')).toUpperCase() || '?')

async function onPhotoChange(e) {
  const file = e.target.files?.[0]
  if (photoInput.value) photoInput.value.value = ''
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    return toast.show('Please choose a JPG, PNG or WebP image.')
  }
  if (file.size > 4 * 1024 * 1024) {
    return toast.show('Photo must be 4MB or smaller.')
  }

  photoUploading.value = true
  try {
    const fd = new FormData()
    fd.append('photo', file)
    const res = await uploadProfilePhoto(fd)
    auth.setUser(res.user)
    Object.assign(user, res.user) // refresh the wizard avatar immediately (no restart needed)
    toast.show('✓ Profile photo updated')
  } catch (e) {
    toast.show(Object.values(e.errors ?? {}).flat()[0] ?? e.message)
  } finally {
    photoUploading.value = false
  }
}

async function removePhoto() {
  photoUploading.value = true
  try {
    const res = await deleteProfilePhoto()
    auth.setUser(res.user)
    Object.assign(user, res.user)
    toast.show('Profile photo removed')
  } catch (e) {
    toast.show(e.message)
  } finally {
    photoUploading.value = false
  }
}

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
    lockedFields.value = d.lockedFields ?? []
    changeRequests.value = d.changeRequests ?? []
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

          <div class="avatar-edit">
            <div class="avatar-ring" :class="{ busy: photoUploading }">
              <img v-if="user.photoUrl" :src="user.photoUrl" alt="Profile photo">
              <span v-else>{{ initials }}</span>
              <button
                class="avatar-cam"
                type="button"
                title="Upload profile photo"
                :disabled="photoUploading"
                @click="photoInput?.click()"
              >{{ photoUploading ? '…' : '📷' }}</button>
            </div>
            <input ref="photoInput" type="file" accept=".jpg,.jpeg,.png,.webp" hidden @change="onPhotoChange">
            <button v-if="user.photoUrl" class="avatar-remove" type="button" @click="removePhoto">Remove photo</button>
          </div>
        </div>

        <div class="row2">
          <div class="field field-locked">
            <label>First Name (English) <span class="req">*</span></label>
            <input v-model="user.firstName" :disabled="isLocked('firstName')" :class="{ invalid: errors.firstName }" placeholder="e.g. Borey">
            <button v-if="isLocked('firstName') && !pendingByField.firstName" class="suggest-btn" @click="openCrModal('firstName')">Request change</button>
            <span v-else-if="isLocked('firstName')" class="lock-hint">⏳ pending</span>
          </div>
          <div class="field field-locked">
            <label>Last Name (English) <span class="req">*</span></label>
            <input v-model="user.lastName" :disabled="isLocked('lastName')" :class="{ invalid: errors.lastName }" placeholder="e.g. Penh">
            <button v-if="isLocked('lastName') && !pendingByField.lastName" class="suggest-btn" @click="openCrModal('lastName')">Request change</button>
            <span v-else-if="isLocked('lastName')" class="lock-hint">⏳ pending</span>
          </div>
        </div>
        <div class="field field-locked">
          <label>Name in Khmer <span style="color:var(--muted); font-weight:400">(ឈ្មោះជាអក្សរខ្មែរ)</span></label>
          <input v-model="user.nameKh" class="kh" :disabled="isLocked('nameKh')" placeholder="សូមបំពេញឈ្មោះជាអក្សរខ្មែរ">
          <button v-if="isLocked('nameKh') && !pendingByField.nameKh" class="suggest-btn" @click="openCrModal('nameKh')">Request change</button>
          <span v-else-if="isLocked('nameKh')" class="lock-hint">⏳ pending</span>
        </div>
        <div class="row2">
          <div class="field">
            <label>Sex <span class="req">*</span></label>
            <select v-model="user.gender" :disabled="isLocked('gender')"><option v-for="g in genderOptions" :key="g">{{ g }}</option></select>
          </div>
          <div class="field">
            <label>Date of Birth <span class="req">*</span></label>
            <input v-model="user.dob" type="date" :disabled="isLocked('dob')">
          </div>
        </div>
        <div class="row2">
          <div class="field">
            <label>Place of Birth <span class="req">*</span></label>
            <input v-model="user.pob" :disabled="isLocked('pob')" placeholder="City / Province">
          </div>
          <div class="field">
            <label>Nationality <span class="req">*</span></label>
            <input v-model="user.nationality" :disabled="isLocked('nationality')" placeholder="e.g. Khmer">
          </div>
        </div>
        <div class="row2">
          <div class="field">
            <label>ID / Passport Number <span class="req">*</span></label>
            <input v-model="user.nid" :disabled="isLocked('nid')">
          </div>
          <div class="field">
            <label>Marital Status <span class="req">*</span></label>
            <select v-model="user.marital" :disabled="isLocked('marital')"><option v-for="m in maritalOptions" :key="m">{{ m }}</option></select>
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
          <span v-if="changeRequests.length" class="help" style="margin-right:auto">
            {{ changeRequests.filter(r => r.status === 'Pending').length }} change request(s) pending with HR
          </span>
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

      <!-- Change requests summary (visible from any step) -->
      <section v-if="changeRequests.length" class="card" style="margin-top:18px">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.pen"></svg></span>
          My Change Requests
        </div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Field</th><th>Requested Value</th><th>Reason</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <tr v-for="r in changeRequests" :key="r.id">
                <td>{{ r.fieldLabel }}</td>
                <td>{{ r.requestedValue }}</td>
                <td>{{ r.reason }}</td>
                <td><StatusBadge :status="r.status" /></td>
                <td>
                  <button v-if="r.status === 'Pending'" class="remove-btn" aria-label="Withdraw" @click="withdrawCr(r.id)">✕</button>
                  <span v-else-if="r.reviewNote" class="help">{{ r.reviewNote }}</span>
                </td>
              </tr>
            </tbody>
          </table>
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

    <!-- Suggest a profile change modal -->
    <div class="modal-overlay" :class="{ show: showCrModal }">
      <div class="modal" v-if="showCrModal" style="max-width:480px">
        <h3>Suggest a Change</h3>
        <p>
          <strong>{{ fieldLabels[crField] }}</strong> is managed by HR. Send your suggested
          value and reason — an administrator will review and apply it if approved.
        </p>

        <div class="field">
          <label>Current Value</label>
          <input :value="user[crField]" disabled>
        </div>
        <div class="field">
          <label>Requested Value <span class="req">*</span></label>
          <input v-model="crValue" :class="{ invalid: crErrors.requestedValue }">
        </div>
        <div class="field">
          <label>Reason <span class="req">*</span></label>
          <textarea v-model="crReason" rows="3" :class="{ invalid: crErrors.reason }"
                    placeholder="Why is this change needed?"></textarea>
        </div>

        <div class="row">
          <button class="btn secondary" @click="showCrModal = false">Cancel</button>
          <button class="btn primary" :disabled="crSaving" @click="sendCr">Send to HR</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.avatar-edit {
  margin-left: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.avatar-ring {
  position: relative;
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--primary-soft, #e6f4f2);
  color: var(--primary, #0e6e66);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 20px;
  overflow: visible;
  flex: none;
}
.avatar-ring img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}
.avatar-ring.busy { opacity: .6; }
.avatar-cam {
  position: absolute;
  right: -2px;
  bottom: -2px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid #fff;
  background: var(--primary, #0e6e66);
  color: #fff;
  font-size: 10px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.avatar-cam:disabled { cursor: wait; }
.avatar-remove {
  background: none;
  border: none;
  color: var(--muted, #64748b);
  font-size: 10.5px;
  cursor: pointer;
  padding: 0;
}
.avatar-remove:hover { color: var(--red, #dc2626); text-decoration: underline; }
</style>
