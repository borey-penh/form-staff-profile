<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  getProfile, savePersonal, saveQualifications, saveSkills, saveFamily,
  uploadDocument, updateDocument, deleteDocument, saveNotes, submitDeclaration,
  submitChangeRequest, cancelChangeRequest,
  uploadProfilePhoto, deleteProfilePhoto,
} from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'
import { COUNTRIES, countryOptions } from '@/utils/countries'
import { calcAge, formatDate } from '@/utils/format'

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const step = ref(1)
const loading = ref(true)
const saving = ref(false)
const errors = ref({})

const user = reactive({})
const quals = ref([])           // education / training / experience / membership
const expertise = ref([])
const motherTongues = ref([])
const languages = ref([])
const geography = ref([])
const spouse = ref({ name: '', occupation: '', phone: '' })
const children = ref([])
const emergency = ref([{ name: '', relationship: '', phone: '', email: '', addr: '' }])
const beneficiaries = ref([])
const documents = ref([])
const docNotes = ref('')
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
  firstName: 'First Name', lastName: 'Surname', nameKh: 'Name (Khmer)',
  dob: 'Date of Birth', gender: 'Gender', pob: 'Place of Birth',
  nationality: 'Citizenship', nid: 'ID / Passport Number', marital: 'Marital Status',
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
  globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>',
  chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
  star: '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01z"/>',
  pin: '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
  users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.4-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.6 2z"/>',
  file: '<path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7z"/><path d="M14 3v4h4"/>',
  upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>',
}

/* ---- Options ---- */
const genderOptions = ['Male', 'Female', 'Non-binary', 'Prefer not to say']
const maritalOptions = ['Married', 'Single']
const maritalOpts = computed(() =>
  user.marital && !maritalOptions.includes(user.marital)
    ? [...maritalOptions, user.marital] // keep legacy values selectable
    : maritalOptions
)
const certificateOptions = ['High School', 'Diploma', 'Bachelor Degree', 'Master Degree', 'Doctorate', 'Vocational', 'Other']
const motherTongueOptions = ['Khmer', 'English', 'Chinese', 'Vietnamese', 'Thai', 'French', 'Other']
const proficiencyOptions = ['Fluent', 'Good', 'Fair', 'Basic']

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
    Object.assign(user, res.user)
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

/* ---- Blank row factories ---- */
const blankQual = (type) => ({ type, title: '', institution: '', field: '', startDate: '', endDate: '', description: '' })

onMounted(load)

async function load() {
  loading.value = true
  try {
    const d = await getProfile()
    Object.assign(user, d.user)
    quals.value = d.qualifications ?? []
    expertise.value = d.expertise ?? []
    motherTongues.value = d.motherTongues ?? []
    languages.value = d.languages ?? []
    geography.value = d.geography ?? []
    spouse.value = d.spouse ?? { name: '', occupation: '', phone: '' }
    children.value = d.children ?? []
    emergency.value = d.emergencyContacts?.length
      ? d.emergencyContacts
      : [{ name: '', relationship: '', phone: '', email: '', addr: '' }]
    beneficiaries.value = d.beneficiaries ?? []
    documents.value = d.documents ?? []
    docNotes.value = d.user?.notesToOrg ?? ''
    lockedFields.value = d.lockedFields ?? []
    changeRequests.value = d.changeRequests ?? []
  } finally {
    loading.value = false
  }
}

/* ---- Derived lists ---- */
const education = computed(() => quals.value.filter((q) => q.type === 'education'))
const trainings = computed(() => quals.value.filter((q) => q.type === 'training'))
const employment = computed(() => quals.value.filter((q) => q.type === 'experience'))
const memberships = computed(() => quals.value.filter((q) => q.type === 'membership'))

function ageNum(dob) {
  if (!dob) return null
  const d = new Date(dob)
  if (isNaN(d)) return null
  let age = new Date().getFullYear() - d.getFullYear()
  const m = new Date().getMonth() - d.getMonth()
  if (m < 0 || (m === 0 && new Date().getDate() < d.getDate())) age--
  return age
}

const minorsCount = computed(() =>
  children.value.filter((c) => { const a = ageNum(c.dob); return a !== null && a < 18 }).length)

const shareTotal = computed(() =>
  Math.round(beneficiaries.value.reduce((sum, b) => sum + (parseFloat(b.share) || 0), 0) * 100) / 100)
const totalClass = computed(() =>
  shareTotal.value > 100 ? 'over' : (shareTotal.value !== 100 ? 'warn' : ''))

/* ---- Tab navigation ---- */
function goHome() {
  router.push('/dashboard')
}

/* ---- Tab 1: save ---- */
async function saveStep1() {
  errors.value = {}
  saving.value = true
  try {
    await savePersonal({
      firstName: user.firstName, lastName: user.lastName, nameKh: user.nameKh,
      dob: user.dob, gender: user.gender, pob: user.pob, nationality: user.nationality,
      nid: user.nid, phone: user.phone, phoneAlt: user.phoneAlt,
      email: user.email, emailAlt: user.emailAlt,
      addrHouse: user.addrHouse, addrStreet: user.addrStreet, addrVillage: user.addrVillage,
      addrCommune: user.addrCommune, addrDistrict: user.addrDistrict,
      addrProvince: user.addrProvince, addrPostal: user.addrPostal,
    })
    toast.show('✓ Personal information saved')
  } catch (e) {
    errors.value = e.errors ?? {}
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

/* ---- Tab 2: save ---- */
async function saveStep2() {
  const items = quals.value.filter((q) => (q.title ?? '').trim() || (q.institution ?? '').trim())
  const bad = items.find((q) => !(q.title ?? '').trim() || !(q.institution ?? '').trim())
  if (bad) {
    toast.show('Each row needs both a name (certificate, course, position or role) and an institution / employer.')
    return
  }
  const payload = {
    expertise: expertise.value.filter((e) => (e.name ?? '').trim()),
    motherTongues: motherTongues.value.filter((l) => (l.language ?? '').trim()),
    languages: languages.value.filter((l) => (l.language ?? '').trim()),
    geography: geography.value.filter((g) => (g.country ?? '').trim()),
  }

  saving.value = true
  try {
    await Promise.all([saveQualifications(items), saveSkills(payload)])
    toast.show('✓ Professional qualifications saved')
  } catch (e) {
    toast.show(Object.values(e.errors ?? {}).flat()[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

/* ---- Tab 3: save ---- */
async function saveStep3() {
  errors.value = {}

  const benefs = beneficiaries.value.filter((b) =>
    (b.fullName ?? '').trim() || (b.idNumber ?? '').trim() || (b.contact ?? '').trim() || (b.address ?? '').trim())
  const badBenef = benefs.find((b) => !(b.fullName ?? '').trim())
  if (badBenef) {
    toast.show('Each beneficiary needs at least a full name.')
    return
  }
  if (benefs.length && shareTotal.value !== 100) {
    toast.show(`Beneficiary shares must total exactly 100% (currently ${shareTotal.value}%).`)
    return
  }

  saving.value = true
  try {
    await saveFamily({
      spouse: spouse.value.name ? spouse.value : null,
      children: children.value,
      emergencyContacts: emergency.value,
      beneficiaries: benefs,
    })
    toast.show('✓ Family information saved')
  } catch (e) {
    errors.value = e.errors ?? {}
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

/* ---- Tab 4: documents ---- */
const newDocTitle = ref('')
const newDocDesc = ref('')
const newDocRemark = ref('')
const newDocFile = ref(null)
const editingDoc = ref(null)

async function upload() {
  if (!newDocTitle.value.trim()) {
    toast.show('Enter a document title first.')
    return
  }
  if (!newDocFile.value) {
    toast.show('Choose a file (PDF, JPG or PNG) first.')
    return
  }
  const fd = new FormData()
  fd.append('type', newDocTitle.value.trim())
  fd.append('description', newDocDesc.value.trim())
  fd.append('remark', newDocRemark.value.trim())
  fd.append('file', newDocFile.value)
  try {
    const res = await uploadDocument(fd)
    documents.value.push(res.document)
    newDocTitle.value = ''
    newDocDesc.value = ''
    newDocRemark.value = ''
    newDocFile.value = null
    toast.show('✓ Document uploaded — pending verification')
  } catch (e) {
    toast.show(Object.values(e.errors ?? {}).flat()[0] ?? e.message)
  }
}

function editDoc(d) {
  editingDoc.value = { id: d.id, type: d.type ?? '', description: d.description ?? '', remark: d.remark ?? '' }
}

async function saveDocEdit() {
  const e = editingDoc.value
  if (!e) return
  if (!e.type.trim()) {
    toast.show('Document title cannot be empty.')
    return
  }
  try {
    const res = await updateDocument(e.id, {
      type: e.type.trim(), description: e.description?.trim() ?? '', remark: e.remark?.trim() ?? '',
    })
    const i = documents.value.findIndex((d) => d.id === e.id)
    if (i !== -1) documents.value[i] = res.document
    editingDoc.value = null
    toast.show('✓ Document updated')
  } catch (err) {
    toast.show(Object.values(err.errors ?? {}).flat()[0] ?? err.message)
  }
}

async function removeDoc(id) {
  try {
    await deleteDocument(id)
    documents.value = documents.value.filter((d) => d.id !== id)
    toast.show('Document deleted')
  } catch (e) {
    toast.show(e.message)
  }
}

async function saveStep4() {
  saving.value = true
  try {
    await saveNotes(docNotes.value)
    toast.show('✓ Notes saved')
  } catch (e) {
    toast.show(Object.values(e.errors ?? {}).flat()[0] ?? e.message)
  } finally {
    saving.value = false
  }
}

/* ---- Tab 5: declaration, preview & print ---- */
async function submitDecl() {
  saving.value = true
  try {
    await submitDeclaration()
    user.declaredAt = new Date().toISOString()
    toast.show('✓ Declaration submitted. Your personnel profile is complete!')
  } catch (e) {
    toast.show(e.message)
  } finally {
    saving.value = false
  }
}

const showPreview = ref(false)
const fullName = computed(() => [user.firstName, user.lastName].filter(Boolean).join(' ') || '—')
const addrFull = computed(() =>
  [user.addrHouse, user.addrStreet, user.addrVillage, user.addrCommune, user.addrDistrict, user.addrProvince, user.addrPostal]
    .filter(Boolean).join(', ') || user.address || '—')

const previewSections = computed(() => {
  const none = '—'
  return [
    {
      title: 'Tab 1 — Personal Information',
      rows: [
        ['Staff ID', user.staffId || none], ['Full name', fullName.value],
        ['Name (Khmer)', user.nameKh || none], ['Gender', user.gender || none],
        ['Date of birth', user.dob || none], ['Place of birth', user.pob || none],
        ['Citizenship', user.nationality || none], ['ID / Passport', user.nid || none],
        ['Primary phone', user.phone || none], ['Alternate phone', user.phoneAlt || none],
        ['Primary email', user.email || none], ['Alternate email', user.emailAlt || none],
        ['Current address', addrFull.value],
      ],
    },
    {
      title: 'Tab 2 — Professional Qualifications',
      rows: [
        ['Education', education.value.map((q) => `${q.title} — ${q.institution}`).join('; ') || none],
        ['Training courses', trainings.value.map((q) => `${q.title} @ ${q.institution}`).join('; ') || none],
        ['Expertise', expertise.value.map((e) => e.name).join(', ') || none],
        ['Employment history', employment.value.map((q) => `${q.title} @ ${q.institution}`).join('; ') || none],
        ['Mother tongue', motherTongues.value.map((l) => l.language).join(', ') || none],
        ['Languages', languages.value.map((l) => `${l.language} (R: ${l.reading || '—'} / W: ${l.writing || '—'} / S: ${l.speaking || '—'} / U: ${l.understanding || '—'})`).join('; ') || none],
        ['Geographic experience', geography.value.map((g) => [g.country, g.province].filter(Boolean).join(' / ')).join('; ') || none],
        ['Memberships', memberships.value.map((q) => `${q.title} @ ${q.institution}`).join('; ') || none],
      ],
    },
    {
      title: 'Tab 3 — Family Information',
      rows: [
        ['Marital status', user.marital || none], ['Spouse / partner', spouse.value.name || none],
        ['Children below 18', String(minorsCount.value)],
        ['Children', children.value.map((c) => `${c.name} (${calcAge(c.dob) || '—'})`).join('; ') || none],
        ['Emergency contacts', emergency.value.filter((e) => e.name).map((e) => `${e.name} (${e.relationship || '—'}) — ${e.phone}`).join('; ') || none],
        ['Beneficiaries', beneficiaries.value.map((b) => `${b.fullName} — ${b.share ?? 0}%`).join('; ') || none],
      ],
    },
    {
      title: 'Tab 4 — Supporting Documents',
      rows: [
        ['Documents', documents.value.map((d) => d.type).join(', ') || none],
        ['Notes to the organization', docNotes.value?.trim() || none],
      ],
    },
  ]
})

async function printProfile() {
  showPreview.value = true
  await nextTick()
  window.print()
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Personnel Profile</h1>
        <div class="sub">Complete all five tabs — your progress is saved per tab.</div>
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
      <!-- ================= TAB 1: PERSONAL INFORMATION ================= -->
      <section v-show="step === 1" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.user"></svg></span>
          Basic Information

          <div class="avatar-edit">
            <div class="avatar-ring" :class="{ busy: photoUploading }">
              <img v-if="user.photoUrl" :src="user.photoUrl" alt="Profile photo">
              <span v-else>{{ initials }}</span>
              <button class="avatar-cam" type="button" title="Upload profile photo" :disabled="photoUploading" @click="photoInput?.click()">{{ photoUploading ? '…' : '📷' }}</button>
            </div>
            <input ref="photoInput" type="file" accept=".jpg,.jpeg,.png,.webp" hidden @change="onPhotoChange">
            <button v-if="user.photoUrl" class="avatar-remove" type="button" @click="removePhoto">Remove photo</button>
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label>Staff ID</label>
            <input :value="user.staffId" disabled>
            <div class="help">Auto generated by the system</div>
          </div>
          <div class="field field-locked">
            <label>First Name <span class="req">*</span></label>
            <input v-model="user.firstName" :disabled="isLocked('firstName')" :class="{ invalid: errors.firstName }" placeholder="e.g. Borey">
            <button v-if="isLocked('firstName') && !pendingByField.firstName" class="suggest-btn" @click="openCrModal('firstName')">Request change</button>
            <span v-else-if="isLocked('firstName')" class="lock-hint">⏳ pending</span>
          </div>
        </div>

        <div class="row2">
          <div class="field field-locked">
            <label>Surname <span class="req">*</span></label>
            <input v-model="user.lastName" :disabled="isLocked('lastName')" :class="{ invalid: errors.lastName }" placeholder="e.g. Penh">
            <button v-if="isLocked('lastName') && !pendingByField.lastName" class="suggest-btn" @click="openCrModal('lastName')">Request change</button>
            <span v-else-if="isLocked('lastName')" class="lock-hint">⏳ pending</span>
          </div>
          <div class="field field-locked">
            <label>Name in Khmer <span style="color:var(--muted); font-weight:400">(ឈ្មោះជាអក្សរខ្មែរ)</span></label>
            <input v-model="user.nameKh" class="kh" :disabled="isLocked('nameKh')" placeholder="សូមបំពេញឈ្មោះជាអក្សរខ្មែរ">
            <button v-if="isLocked('nameKh') && !pendingByField.nameKh" class="suggest-btn" @click="openCrModal('nameKh')">Request change</button>
            <span v-else-if="isLocked('nameKh')" class="lock-hint">⏳ pending</span>
          </div>
        </div>

        <div class="row2">
          <div class="field field-locked">
            <label>Gender <span class="req">*</span></label>
            <select v-model="user.gender" :disabled="isLocked('gender')">
              <option v-for="g in genderOptions" :key="g" :value="g">{{ g }}</option>
            </select>
            <button v-if="isLocked('gender') && !pendingByField.gender" class="suggest-btn" @click="openCrModal('gender')">Request change</button>
            <span v-else-if="isLocked('gender')" class="lock-hint">⏳ pending</span>
          </div>
          <div class="field field-locked">
            <label>Date of Birth <span class="req">*</span></label>
            <input v-model="user.dob" type="date" :disabled="isLocked('dob')">
            <button v-if="isLocked('dob') && !pendingByField.dob" class="suggest-btn" @click="openCrModal('dob')">Request change</button>
            <span v-else-if="isLocked('dob')" class="lock-hint">⏳ pending</span>
          </div>
        </div>

        <div class="row2">
          <div class="field field-locked">
            <label>Place of Birth <span class="req">*</span></label>
            <input v-model="user.pob" :disabled="isLocked('pob')" placeholder="City / Province">
            <button v-if="isLocked('pob') && !pendingByField.pob" class="suggest-btn" @click="openCrModal('pob')">Request change</button>
            <span v-else-if="isLocked('pob')" class="lock-hint">⏳ pending</span>
          </div>
          <div class="field field-locked">
            <label>Citizenship (if foreigner)</label>
            <select v-model="user.nationality" :disabled="isLocked('nationality')">
              <option v-for="c in countryOptions(user.nationality)" :key="c" :value="c">{{ c }}</option>
            </select>
            <button v-if="isLocked('nationality') && !pendingByField.nationality" class="suggest-btn" @click="openCrModal('nationality')">Request change</button>
            <span v-else-if="isLocked('nationality')" class="lock-hint">⏳ pending</span>
          </div>
        </div>

        <div class="field field-locked">
          <label>ID / Passport Number <span class="req">*</span></label>
          <input v-model="user.nid" :disabled="isLocked('nid')" :class="{ invalid: errors.nid }">
          <button v-if="isLocked('nid') && !pendingByField.nid" class="suggest-btn" @click="openCrModal('nid')">Request change</button>
          <span v-else-if="isLocked('nid')" class="lock-hint">⏳ pending</span>
        </div>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.phone"></svg></span>
          Contact Information
        </div>

        <div class="row2">
          <div class="field">
            <label>Primary phone number <span class="req">*</span></label>
            <input v-model="user.phone" type="tel" placeholder="e.g. 012 345 678" :class="{ invalid: errors.phone }">
          </div>
          <div class="field">
            <label>Alternate phone number</label>
            <input v-model="user.phoneAlt" type="tel" placeholder="e.g. 092 111 222">
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label>Primary email address <span class="req">*</span></label>
            <input v-model="user.email" type="email" placeholder="e.g. name@example.com" :class="{ invalid: errors.email }">
          </div>
          <div class="field">
            <label>Alternate email address</label>
            <input v-model="user.emailAlt" type="email" placeholder="e.g. name@personal.com" :class="{ invalid: errors.emailAlt }">
          </div>
        </div>

        <div class="help" style="margin:18px 0 6px">Current address</div>
        <div class="row2">
          <div class="field"><label>House number</label><input v-model="user.addrHouse" placeholder="e.g. #42B"></div>
          <div class="field"><label>Street number</label><input v-model="user.addrStreet" placeholder="e.g. St. 310"></div>
        </div>
        <div class="row2">
          <div class="field"><label>Village</label><input v-model="user.addrVillage" placeholder="e.g. Phum Boeng Keng Kang I"></div>
          <div class="field"><label>Commune</label><input v-model="user.addrCommune" placeholder="e.g. Sangkat Boeng Keng Kang I"></div>
        </div>
        <div class="row2">
          <div class="field"><label>District</label><input v-model="user.addrDistrict" placeholder="e.g. Chamkarmon"></div>
          <div class="field"><label>Province</label><input v-model="user.addrProvince" placeholder="e.g. Phnom Penh"></div>
        </div>
        <div class="field"><label>Postal Code</label><input v-model="user.addrPostal" placeholder="e.g. 12310"></div>

        <div class="actions">
          <button class="btn secondary" @click="goHome">Home</button>
          <span class="help" style="margin-right:auto">Tab {{ step }} of 5</span>
          <button class="btn secondary" :disabled="saving" @click="saveStep1">Save</button>
          <button class="btn secondary" @click="goHome">Exit</button>
          <button class="btn primary" @click="step = 2">Next →</button>
        </div>
      </section>

      <!-- ================= TAB 2: PROFESSIONAL QUALIFICATIONS ================= -->
      <section v-show="step === 2" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.cap"></svg></span>
          Education History
          <span class="count">{{ education.length }}</span>
        </div>

        <div v-for="(q, i) in education" :key="'e' + (q.id ?? i)" class="item-row">
          <div class="grow">
            <div class="row-eq">
              <div class="field"><label>Start Date</label><input v-model="q.startDate" type="date"></div>
              <div class="field"><label>End Date</label><input v-model="q.endDate" type="date"></div>
              <div class="field"><label>Educational Institution</label><input v-model="q.institution"></div>
              <div class="field"><label>Certificate / Diploma</label>
                <select v-model="q.title"><option v-for="c in certificateOptions" :key="c" :value="c">{{ c }}</option></select>
              </div>
              <div class="field"><label>Major</label><input v-model="q.field"></div>
            </div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="quals.splice(quals.indexOf(q), 1)">✕</button>
        </div>
        <button class="add-btn" @click="quals.push(blankQual('education'))">+ Add education background</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.star"></svg></span>
          Training Courses
          <span class="count">{{ trainings.length }}</span>
        </div>

        <div v-for="(q, i) in trainings" :key="'t' + (q.id ?? i)" class="item-row">
          <div class="grow">
            <div class="row-eq">
              <div class="field"><label>Course title</label><input v-model="q.title"></div>
              <div class="field"><label>Period — From</label><input v-model="q.startDate" type="date"></div>
              <div class="field"><label>Period — To</label><input v-model="q.endDate" type="date"></div>
              <div class="field"><label>Training Provider</label><input v-model="q.institution"></div>
            </div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="quals.splice(quals.indexOf(q), 1)">✕</button>
        </div>
        <button class="add-btn" @click="quals.push(blankQual('training'))">+ Add training course</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.check"></svg></span>
          Expertise
          <span class="count">{{ expertise.length }}</span>
        </div>

        <div v-for="(e, i) in expertise" :key="'x' + (e.id ?? i)" class="item-row">
          <div class="grow row2" style="align-items:end">
            <div class="field"><label>Expertise</label><input v-model="e.name" placeholder="e.g. Community facilitation"></div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="expertise.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" @click="expertise.push({ name: '' })">+ Add expertise</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.briefcase"></svg></span>
          Employment History
          <span class="count">{{ employment.length }}</span>
        </div>

        <div v-for="(q, i) in employment" :key="'w' + (q.id ?? i)" class="item-row">
          <div class="grow">
            <div class="row-eq">
              <div class="field"><label>Start date</label><input v-model="q.startDate" type="date"></div>
              <div class="field"><label>End date</label><input v-model="q.endDate" type="date"></div>
              <div class="field"><label>Position</label><input v-model="q.title"></div>
              <div class="field"><label>Employer</label><input v-model="q.institution"></div>
              <div class="field"><label>Project title</label><input v-model="q.field"></div>
            </div>
            <div class="field"><label>Job description</label><textarea v-model="q.description" rows="2"></textarea></div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="quals.splice(quals.indexOf(q), 1)">✕</button>
        </div>
        <button class="add-btn" @click="quals.push(blankQual('experience'))">+ Add work experience</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.chat"></svg></span>
          Mother Tongue Language
          <span class="count">{{ motherTongues.length }}</span>
        </div>

        <div v-for="(l, i) in motherTongues" :key="'mt' + (l.id ?? i)" class="item-row">
          <div class="grow row2" style="align-items:end">
            <div class="field">
              <label>Language</label>
              <select v-model="l.language">
                <option v-for="m in motherTongueOptions" :key="m" :value="m">{{ m }}</option>
              </select>
            </div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="motherTongues.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" @click="motherTongues.push({ language: 'Khmer' })">+ Add mother tongue language</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.chat"></svg></span>
          Foreign / Second Language Proficiency
          <span class="count">{{ languages.length }}</span>
        </div>

        <div v-for="(l, i) in languages" :key="'fl' + (l.id ?? i)" class="item-row">
          <div class="grow">
            <div class="row-eq">
              <div class="field"><label>Language</label><input v-model="l.language" placeholder="e.g. English"></div>
              <div class="field"><label>Reading</label><select v-model="l.reading"><option value="">—</option><option v-for="p in proficiencyOptions" :key="p" :value="p">{{ p }}</option></select></div>
              <div class="field"><label>Writing</label><select v-model="l.writing"><option value="">—</option><option v-for="p in proficiencyOptions" :key="p" :value="p">{{ p }}</option></select></div>
              <div class="field"><label>Speaking</label><select v-model="l.speaking"><option value="">—</option><option v-for="p in proficiencyOptions" :key="p" :value="p">{{ p }}</option></select></div>
              <div class="field"><label>Understanding</label><select v-model="l.understanding"><option value="">—</option><option v-for="p in proficiencyOptions" :key="p" :value="p">{{ p }}</option></select></div>
            </div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="languages.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" @click="languages.push({ language: '', reading: '', writing: '', speaking: '', understanding: '' })">+ Add language</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.globe"></svg></span>
          Geographic Experience
          <span class="count">{{ geography.length }}</span>
        </div>

        <div v-for="(g, i) in geography" :key="'g' + (g.id ?? i)" class="item-row">
          <div class="grow row2" style="align-items:end">
            <div class="field">
              <label>Country</label>
              <select v-model="g.country"><option value="">Select a country…</option><option v-for="c in COUNTRIES" :key="c" :value="c">{{ c }}</option></select>
            </div>
            <div class="field"><label>Province / Region</label><input v-model="g.province"></div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="geography.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" @click="geography.push({ country: '', province: '' })">+ Add geography experience</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.users"></svg></span>
          Membership, Association or Affiliation
          <span class="count">{{ memberships.length }}</span>
        </div>

        <div v-for="(q, i) in memberships" :key="'m' + (q.id ?? i)" class="item-row">
          <div class="grow">
            <div class="row-eq">
              <div class="field"><label>Period — From</label><input v-model="q.startDate" type="date"></div>
              <div class="field"><label>Period — To</label><input v-model="q.endDate" type="date"></div>
              <div class="field"><label>Institution</label><input v-model="q.institution"></div>
              <div class="field"><label>Role / Position</label><input v-model="q.title"></div>
            </div>
            <div class="field"><label>Description of Work</label><textarea v-model="q.description" rows="2"></textarea></div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="quals.splice(quals.indexOf(q), 1)">✕</button>
        </div>
        <button class="add-btn" @click="quals.push(blankQual('membership'))">+ Add Membership, Association or Affiliation</button>

        <div class="actions">
          <button class="btn secondary" @click="goHome">Home</button>
          <span class="help" style="margin-right:auto">Tab {{ step }} of 5</span>
          <button class="btn secondary" @click="step = 1">← Previous</button>
          <button class="btn secondary" :disabled="saving" @click="saveStep2">Save</button>
          <button class="btn secondary" @click="goHome">Exit</button>
          <button class="btn primary" @click="step = 3">Next →</button>
        </div>
      </section>

      <!-- ================= TAB 3: FAMILY INFORMATION ================= -->
      <section v-show="step === 3" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.heart"></svg></span>
          Family Situation
        </div>

        <div class="row2">
          <div class="field field-locked">
            <label>Marital status</label>
            <select v-model="user.marital" :disabled="isLocked('marital')">
              <option v-for="m in maritalOpts" :key="m" :value="m">{{ m }}</option>
            </select>
            <button v-if="isLocked('marital') && !pendingByField.marital" class="suggest-btn" @click="openCrModal('marital')">Request change</button>
            <span v-else-if="isLocked('marital')" class="lock-hint">⏳ pending</span>
          </div>
          <div class="field">
            <label>Spouse / partner name</label>
            <input v-model="spouse.name" placeholder="e.g. Chanly Chea">
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label>Number of children (below 18 years old)</label>
            <input :value="minorsCount" disabled>
            <div class="help">Automatically counted from the children below.</div>
          </div>
        </div>

        <div class="help" style="margin:14px 0 6px">Children detail</div>
        <div v-for="(c, i) in children" :key="'c' + (c.id ?? i)" class="item-row">
          <div class="grow row3" style="align-items:end">
            <div class="field"><label>Name</label><input v-model="c.name"></div>
            <div class="field"><label>Date of Birth</label><input v-model="c.dob" type="date"></div>
            <div class="field"><label>Sex</label>
              <select v-model="c.gender"><option>Male</option><option>Female</option><option>Other</option></select>
            </div>
          </div>
          <span v-if="calcAge(c.dob)" class="help">{{ calcAge(c.dob) }}</span>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="children.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" style="margin-bottom:22px" @click="children.push({ name: '', dob: '', gender: 'Male', status: 'Alive' })">+ Add child</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.phone"></svg></span>
          Emergency Contact
          <span class="count">{{ emergency.filter(e => e.name).length }}</span>
        </div>

        <div v-for="(e, i) in emergency" :key="'ec' + i" class="item-row">
          <div class="grow">
            <div class="row3">
              <div class="field"><label>Name <span class="req">*</span></label><input v-model="e.name"></div>
              <div class="field"><label>Relationship <span class="req">*</span></label><input v-model="e.relationship" placeholder="e.g. Father, Spouse"></div>
              <div class="field"><label>Phone <span class="req">*</span></label><input v-model="e.phone" type="tel"></div>
            </div>
            <div class="row2">
              <div class="field"><label>Email</label><input v-model="e.email" type="email"></div>
              <div class="field"><label>Current Address</label><input v-model="e.addr"></div>
            </div>
          </div>
          <button v-if="emergency.length > 1" class="remove-btn" aria-label="Delete" title="Delete" @click="emergency.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" @click="emergency.push({ name: '', relationship: '', phone: '', email: '', addr: '' })">+ Add emergency contact</button>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.users"></svg></span>
          Beneficiaries
          <span class="count">{{ beneficiaries.length }}</span>
          <span class="help" style="margin-left:auto">Total share: {{ shareTotal }}%</span>
        </div>

        <div v-for="(b, i) in beneficiaries" :key="'b' + (b.id ?? i)" class="item-row">
          <div class="grow">
            <div class="row3">
              <div class="field"><label>Full Name <span class="req">*</span></label><input v-model="b.fullName"></div>
              <div class="field"><label>Date of Birth</label><input v-model="b.dob" type="date"></div>
              <div class="field"><label>Birth certificate / ID / Passport No.</label><input v-model="b.idNumber"></div>
            </div>
            <div class="row3">
              <div class="field"><label>Relationship</label><input v-model="b.relationship" placeholder="e.g. Spouse"></div>
              <div class="field"><label>Contact Number</label><input v-model="b.contact" type="tel"></div>
              <div class="field"><label>Share of Benefits (%)</label><input v-model="b.share" type="number" min="0" max="100" placeholder="0"></div>
            </div>
            <div class="field"><label>Address</label><input v-model="b.address"></div>
          </div>
          <button class="remove-btn" aria-label="Delete" title="Delete" @click="beneficiaries.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" @click="beneficiaries.push({ fullName: '', dob: '', idNumber: '', relationship: '', contact: '', address: '', share: '' })">+ Add beneficiary</button>

        <div class="actions">
          <button class="btn secondary" @click="goHome">Home</button>
          <span class="help" style="margin-right:auto">Tab {{ step }} of 5</span>
          <button class="btn secondary" @click="step = 2">← Previous</button>
          <button class="btn secondary" :disabled="saving" @click="saveStep3">Save</button>
          <button class="btn secondary" @click="goHome">Exit</button>
          <button class="btn primary" @click="step = 4">Next →</button>
        </div>
      </section>

      <!-- ================= TAB 4: SUPPORTING DOCUMENTS ================= -->
      <section v-show="step === 4" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.file"></svg></span>
          Attachments
        </div>
        <div class="card-desc" style="margin-top:-6px">
          Acceptable formats: <strong>PDF, JPG, PNG</strong>. Examples of attachments to provide:
        </div>
        <ul class="attach-list">
          <li>ID card / birth certificate / passport of yourself and people you listed in this database</li>
          <li>Valid visa, if you are not Cambodian</li>
          <li>Work permit, if you are not Cambodian</li>
          <li>Employment book, if applicable</li>
          <li>Family record book, if you are married</li>
          <li>Residential book, or immigration report for foreigners</li>
          <li>Certificates</li>
        </ul>

        <div v-if="documents.length" class="table-wrap" style="margin:18px 0">
          <table class="table">
            <thead>
              <tr>
                <th>Document title</th>
                <th>Description</th>
                <th>Uploading Date</th>
                <th>Remark</th>
                <th>File</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in documents" :key="d.id">
                <template v-if="editingDoc?.id === d.id">
                  <td><input v-model="editingDoc.type"></td>
                  <td><input v-model="editingDoc.description"></td>
                  <td>{{ formatDate(d.uploadedAt) }}</td>
                  <td><input v-model="editingDoc.remark"></td>
                  <td></td>
                  <td><StatusBadge :status="d.status" /></td>
                  <td style="white-space:nowrap">
                    <button class="btn secondary" @click="saveDocEdit">Save</button>
                    <button class="btn secondary" @click="editingDoc = null">Cancel</button>
                  </td>
                </template>
                <template v-else>
                  <td>{{ d.type }}</td>
                  <td>{{ d.description || '—' }}</td>
                  <td>{{ formatDate(d.uploadedAt) }}</td>
                  <td>{{ d.remark || '—' }}</td>
                  <td><a :href="d.url" target="_blank">{{ d.originalName }}</a></td>
                  <td><StatusBadge :status="d.status" /></td>
                  <td style="white-space:nowrap">
                    <button class="suggest-btn" @click="editDoc(d)">Edit</button>
                    <button class="remove-btn" aria-label="Delete" title="Delete" @click="removeDoc(d.id)">✕</button>
                  </td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState
          v-else
          title="No documents uploaded yet"
          desc="Add your ID, family record book or certificates below."
          style="padding:26px 10px"
        />

        <div class="upload-box">
          <div class="row2" style="flex:1; align-items:end; gap:12px; margin:0">
            <div class="field" style="margin:0">
              <label>Document title</label>
              <input v-model="newDocTitle" placeholder="e.g. My passport">
            </div>
            <div class="field" style="margin:0">
              <label>Description</label>
              <input v-model="newDocDesc" placeholder="What does this document show?">
            </div>
          </div>
          <div class="row2" style="flex:1; align-items:end; gap:12px; margin:0">
            <div class="field" style="margin:0">
              <label>Remark</label>
              <input v-model="newDocRemark">
            </div>
            <div class="field" style="margin:0">
              <label>File (PDF/JPG/PNG, max 10MB)</label>
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="e => newDocFile = e.target.files[0]">
            </div>
          </div>
          <button class="btn primary" @click="upload">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.upload"></svg>
            Add document
          </button>
        </div>

        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.pen"></svg></span>
          Your notes to the organization
        </div>
        <div class="field">
          <textarea v-model="docNotes" rows="5" placeholder="Anything else you would like to tell the organization…"></textarea>
        </div>

        <div class="actions">
          <button class="btn secondary" @click="goHome">Home</button>
          <span class="help" style="margin-right:auto">Tab {{ step }} of 5</span>
          <button class="btn secondary" @click="step = 3">← Previous</button>
          <button class="btn secondary" :disabled="saving" @click="saveStep4">Save</button>
          <button class="btn secondary" @click="goHome">Exit</button>
          <button class="btn primary" @click="step = 5">Next →</button>
        </div>
      </section>

      <!-- Change requests summary (visible from any tab) -->
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

      <!-- ================= TAB 5: DECLARATION ================= -->
      <section v-show="step === 5" class="card">
        <div class="sub-head">
          <span class="sh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="sIcons.pen"></svg></span>
          Declaration
        </div>

        <p class="decl-text">
          I confirm that all details and documents submitted in this Personnel Profile are complete,
          true, and correct to the best of my knowledge. I understand that any false, misleading, or
          suppressed information may result in immediate disciplinary action or termination of
          employment in accordance with the organization policy. I undertake to inform administration
          and HR team immediately of any changes to this information.
        </p>

        <div v-if="user.declaredAt" class="help">
          ✓ Declaration submitted on {{ formatDate(user.declaredAt) }}.
        </div>

        <div class="actions">
          <span class="help" style="margin-right:auto">Tab {{ step }} of 5</span>
          <button class="btn secondary" @click="step = 4">← Previous</button>
          <button class="btn secondary" @click="showPreview = true">👁 Preview</button>
          <button class="btn secondary" @click="printProfile">🖨 Print</button>
          <button class="btn primary" :disabled="saving" @click="submitDecl">✓ Submit</button>
          <button class="btn secondary" @click="goHome">Exit</button>
        </div>
      </section>
    </template>

    <!-- Preview / print overlay -->
    <div class="modal-overlay" :class="{ show: showPreview }">
      <div class="modal preview-modal print-area" v-if="showPreview">
        <div class="preview-head no-print">
          <h3>Personnel Profile — Preview</h3>
          <div>
            <button class="btn secondary" @click="printProfile">🖨 Print</button>
            <button class="btn secondary" @click="showPreview = false">Close</button>
          </div>
        </div>

        <div class="preview-title">Personnel Profile</div>
        <div class="preview-sub">{{ fullName }} · Staff ID {{ user.staffId || '—' }} · {{ formatDate(new Date().toISOString()) }}</div>

        <section v-for="s in previewSections" :key="s.title" class="preview-sec">
          <h4>{{ s.title }}</h4>
          <table class="table preview-table">
            <tbody>
              <tr v-for="row in s.rows" :key="row[0]">
                <th>{{ row[0] }}</th>
                <td>{{ row[1] }}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    </div>

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

/* Single-line entry rows (education, training, employment, languages, …) */
.row-eq {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
}
.attach-list {
  margin: 10px 0 0;
  padding-left: 22px;
  color: var(--muted, #64748b);
  font-size: 13px;
  line-height: 1.7;
}
.decl-text {
  background: var(--primary-soft, #f6f8fa);
  border-left: 3px solid var(--primary, #0e6e66);
  border-radius: 6px;
  padding: 14px 16px;
  line-height: 1.7;
  font-size: 14px;
}
.preview-modal { max-width: 760px; width: 92%; max-height: 84vh; overflow: auto; }
.preview-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.preview-title { font-size: 20px; font-weight: 800; }
.preview-sub { color: var(--muted, #64748b); margin: 2px 0 14px; font-size: 12.5px; }
.preview-sec h4 {
  margin: 16px 0 6px;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--primary, #0e6e66);
}
.preview-table th {
  width: 34%;
  text-align: left;
  color: var(--muted, #64748b);
  font-weight: 600;
  vertical-align: top;
}

@media print {
  body * { visibility: hidden !important; }
  .print-area, .print-area * { visibility: visible !important; }
  .print-area {
    position: absolute;
    inset: 0;
    max-height: none;
    width: 100%;
    box-shadow: none;
  }
  .no-print { display: none !important; }
}
</style>

