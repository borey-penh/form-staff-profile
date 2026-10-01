<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import SegmentedControl from '@/components/SegmentedControl.vue'
import SignaturePad from '@/components/SignaturePad.vue'
import SuccessModal from '@/components/SuccessModal.vue'
import { useStaffProfilesStore } from '@/stores/staffProfiles'
import { useToastStore } from '@/stores/toast'
import { calcAge, todayISO } from '@/utils/format'

const router = useRouter()
const store = useStaffProfilesStore()

/* ---------- Form state ---------- */
const blankChild = () => ({ name: '', rel: 'Son', dob: '' })
const blankBenef = () => ({ name: '', rel: 'Spouse', dob: '', cert: '', contact: '', addr: '', share: '' })

const blankForm = () => ({
  nameEn: '', nameKh: '', gender: 'Male', dob: '', pob: '', nid: '',
  addr: '', phone: '', email: '',
  marital: 'Married', spouseName: '', spouseOcc: '',
  children: [],
  ecName: '', ecRel: '', ecPhone: '', ecAddr: '',
  beneficiaries: [blankBenef()],
  confirmed: false,
  signature: null,
})

const form = reactive(blankForm())
const fieldErrors = ref({})
const sigPad = ref(null)
const showSuccess = ref(false)
const successMsg = ref('')

const genderOptions = [
  { value: 'Male', label: 'Male' },
  { value: 'Female', label: 'Female' },
  { value: 'Other', label: 'Other' },
]
const maritalOptions = [
  { value: 'Married', label: 'Married' },
  { value: 'Single', label: 'Single' },
]
const childRelOptions = [
  { value: 'Son', label: 'Son' },
  { value: 'Daughter', label: 'Daughter' },
]
const ecRelOptions = [
  'Father', 'Mother', 'Spouse',
  'Brother / Sibling (បងប្អូនប្រុស)',
  'Sister / Sibling (បងប្អូនស្រី)',
  'Other relative', 'Friend',
]
const benefRelOptions = ['Spouse', 'Father', 'Mother', 'Son', 'Daughter', 'Sibling', 'Other']

/* ---------- Derived ---------- */
const showSpouse = computed(() => form.marital === 'Married')
const sigDate = todayISO()
const sigName = computed(() => form.nameEn.trim() || '—')

const shareTotal = computed(() =>
  Math.round(form.beneficiaries.reduce((sum, b) => sum + (parseFloat(b.share) || 0), 0) * 100) / 100
)
const totalClass = computed(() =>
  shareTotal.value > 100 ? 'over' : (shareTotal.value !== 100 ? 'warn' : '')
)

/* ---------- List helpers ---------- */
function addChild() { form.children.push(blankChild()) }
function removeChild(i) { form.children.splice(i, 1) }
function addBeneficiary() { form.beneficiaries.push(blankBenef()) }
function removeBeneficiary(i) { form.beneficiaries.splice(i, 1) }

/* ---------- Draft (localStorage) ---------- */
const DRAFT_KEY = 'staffProfileDraft'
function saveDraft() {
  localStorage.setItem(DRAFT_KEY, JSON.stringify(form))
  useToastStore().show('💾 Draft saved in this browser.')
}
function restoreDraft() {
  const raw = localStorage.getItem(DRAFT_KEY)
  if (!raw) return
  try {
    Object.assign(form, blankForm(), JSON.parse(raw))
  } catch { /* ignore corrupted draft */ }
}

/* ---------- Submit ---------- */
async function submit() {
  fieldErrors.value = {}

  if (form.beneficiaries.length === 0) {
    useToastStore().show('Please add at least one insurance beneficiary.')
    return
  }
  if (shareTotal.value !== 100) {
    useToastStore().show(`Beneficiary shares must total exactly 100% (currently ${shareTotal.value}%).`)
    return
  }
  if (!form.confirmed) {
    useToastStore().show('Please confirm the information is true and accurate (checkbox in section 4).')
    return
  }
  if (!form.signature && !confirm('The signature pad is empty. Submit without a signature?')) return

  try {
    const res = await store.submit({ ...form })
    successMsg.value = res.message || `The staff profile for ${form.nameEn} has been saved.`
    showSuccess.value = true
    localStorage.removeItem(DRAFT_KEY)
  } catch (err) {
    fieldErrors.value = err.errors ?? {}
    const first = Object.values(err.errors ?? {})[0]
    useToastStore().show(Array.isArray(first) ? first[0] : err.message)
    const el = document.querySelector('.invalid')
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
}

function onSuccessClose() {
  showSuccess.value = false
  Object.assign(form, blankForm())
  router.push({ name: 'staff-profiles.index' })
}

onMounted(restoreDraft)
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Staff Profile</h1>
        <div class="sub">
          Complete all four sections below. Fields marked
          <span style="color: var(--red)">*</span> are required.
        </div>
      </div>
      <div class="form-no">HR Form • HR-EMP-01</div>
    </div>

    <!-- ============ 1. PERSONAL INFORMATION ============ -->
    <section class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
        <h2>1. Personal Information</h2>
      </div>

      <div class="row2">
        <div class="field">
          <label>Name in English (Latin) <span class="req">*</span></label>
          <input v-model="form.nameEn" type="text" autocomplete="name" placeholder="e.g. Sokha Meng" :class="{ invalid: fieldErrors.nameEn }">
          <div class="help">Spelled as shown in passport or legal ID</div>
        </div>
        <div class="field">
          <label>Name in Khmer <span class="khmer">(ឈ្មោះជាអក្សរខ្មែរ)</span> <span class="req">*</span></label>
          <input v-model="form.nameKh" type="text" class="kh" placeholder="សូមបំពេញឈ្មោះជាអក្សរខ្មែរ" :class="{ invalid: fieldErrors.nameKh }">
          <div class="help kh">សូមបំពេញតាមឈ្មោះនៅក្នុងអត្តសញ្ញាណប័ណ្ណ</div>
        </div>
      </div>

      <div class="field">
        <label>Sex / Gender <span class="req">*</span></label>
        <SegmentedControl v-model="form.gender" :options="genderOptions" />
      </div>

      <div class="row2">
        <div class="field">
          <label>Date of Birth <span class="req">*</span></label>
          <input v-model="form.dob" type="date" :class="{ invalid: fieldErrors.dob }">
        </div>
        <div class="field">
          <label>Place of Birth <span class="req">*</span></label>
          <input v-model="form.pob" type="text" placeholder="e.g. Battambang Province, Cambodia" :class="{ invalid: fieldErrors.pob }">
        </div>
      </div>

      <div class="field">
        <label>National ID or Passport Number <span class="req">*</span></label>
        <input v-model="form.nid" type="text" placeholder="e.g. 010892415" :class="{ invalid: fieldErrors.nid }">
      </div>

      <div class="field">
        <label>Current Residential Address <span class="req">*</span></label>
        <input v-model="form.addr" type="text" placeholder="#42B, St. 310, Sangkat Boeng Keng Kang I, Phnom Penh" :class="{ invalid: fieldErrors.addr }">
      </div>

      <div class="row2">
        <div class="field">
          <label>Mobile Phone <span class="req">*</span></label>
          <input v-model="form.phone" type="tel" placeholder="+855 12 849 201" :class="{ invalid: fieldErrors.phone }">
        </div>
        <div class="field">
          <label>Email Address <span class="req">*</span></label>
          <input v-model="form.email" type="email" placeholder="sokha.meng@company.com" :class="{ invalid: fieldErrors.email }">
        </div>
      </div>

      <div class="marital-row">
        <label>Marital Status</label>
        <SegmentedControl v-model="form.marital" :options="maritalOptions" mini />
      </div>

      <div v-show="showSpouse" class="row2">
        <div class="field">
          <label>Spouse's Full Name</label>
          <input v-model="form.spouseName" type="text" placeholder="e.g. Chanly Chea">
        </div>
        <div class="field">
          <label>Spouse's Occupation</label>
          <input v-model="form.spouseOcc" type="text" placeholder="e.g. Senior Financial Analyst">
        </div>
      </div>

      <div class="children-block">
        <div class="children-label">Declared Children ({{ form.children.length }})</div>
        <div v-if="form.children.length === 0" class="empty-note">
          No children declared. Use the button below to add one.
        </div>
        <div v-for="(child, i) in form.children" :key="i" class="child-row">
          <span class="idx">{{ i + 1 }}.</span>
          <input v-model="child.name" type="text" class="c-name" placeholder="Child full name">
          <select v-model="child.rel" class="c-rel">
            <option v-for="opt in childRelOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <input v-model="child.dob" type="date" class="c-dob">
          <span class="c-age">{{ calcAge(child.dob) }}</span>
          <button type="button" class="remove-btn" title="Remove" @click="removeChild(i)">✕</button>
        </div>
        <button type="button" class="add-btn" @click="addChild">+ Add Child</button>
      </div>
    </section>

    <!-- ============ 2. EMERGENCY CONTACT ============ -->
    <section class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.4" stroke-linecap="round"><path d="M12 4v16M5 8l14 8M19 8L5 16"/></svg>
        <h2>2. Emergency Contact</h2>
      </div>
      <div class="card-desc">Who should we contact directly in case of an unexpected emergency or accident?</div>

      <div class="row2">
        <div class="field">
          <label>Contact Full Name <span class="req">*</span></label>
          <input v-model="form.ecName" type="text" placeholder="e.g. Rathana Meng" :class="{ invalid: fieldErrors.ecName }">
        </div>
        <div class="field">
          <label>Relationship <span class="req">*</span></label>
          <select v-model="form.ecRel" :class="{ invalid: fieldErrors.ecRel }">
            <option value="" disabled>Select relationship…</option>
            <option v-for="rel in ecRelOptions" :key="rel" :value="rel">{{ rel }}</option>
          </select>
        </div>
      </div>

      <div class="row2">
        <div class="field">
          <label>Emergency Phone Number <span class="req">*</span></label>
          <input v-model="form.ecPhone" type="tel" placeholder="+855 16 772 390" :class="{ invalid: fieldErrors.ecPhone }">
        </div>
        <div class="field">
          <label>Current Address <span class="req">*</span></label>
          <input v-model="form.ecAddr" type="text" placeholder="#15, St. 214, Phnom Penh" :class="{ invalid: fieldErrors.ecAddr }">
        </div>
      </div>
    </section>

    <!-- ============ 3. INSURANCE BENEFICIARIES ============ -->
    <section class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <h2>3. Insurance Beneficiaries</h2>
        <div class="spacer"></div>
        <div class="total-badge" :class="totalClass">Total: {{ shareTotal }}%</div>
      </div>
      <div class="card-desc">
        Please list the family members who will receive your statutory insurance benefits
        (must be your affiliated family). Total share must equal 100%.
      </div>

      <div v-for="(b, i) in form.beneficiaries" :key="i" class="benef-card">
        <div class="benef-top">
          <input v-model="b.name" type="text" class="b-name" placeholder="Beneficiary full name" :class="{ invalid: fieldErrors[`beneficiaries.${i}.name`] }">
          <div class="share-wrap">
            <span class="share-label">Share</span>
            <input v-model="b.share" type="number" min="0" max="100" placeholder="0" :class="{ invalid: fieldErrors[`beneficiaries.${i}.share`] }">
            <span class="pct-badge">{{ parseFloat(b.share) || 0 }}%</span>
          </div>
          <button type="button" class="remove-btn" title="Remove" @click="removeBeneficiary(i)">✕</button>
        </div>
        <div class="benef-grid">
          <select v-model="b.rel">
            <option v-for="rel in benefRelOptions" :key="rel" :value="rel">{{ rel }}</option>
          </select>
          <input v-model="b.dob" type="date" title="Date of Birth">
          <input v-model="b.cert" type="text" placeholder="Birth certificate #">
          <input v-model="b.contact" type="tel" placeholder="Contact number">
        </div>
        <div class="benef-addr">
          <input v-model="b.addr" type="text" placeholder="Address">
        </div>
      </div>
      <button type="button" class="add-btn" @click="addBeneficiary">+ Add Beneficiary</button>
    </section>

    <!-- ============ 4. CONFIRMATION & SIGNATURE ============ -->
    <section class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        <h2>4. Confirmation &amp; Signature</h2>
      </div>

      <div class="note-block">
        <div class="quote">"Note: The information I stated above are certainty."</div>
        <div class="law">Under Cambodian Labor Law, all submitted details are held official for your employment record.</div>
      </div>

      <div class="confirm-row">
        <input id="confirmChk" v-model="form.confirmed" type="checkbox">
        <label for="confirmChk">I confirm the information provided in this form is true and accurate.</label>
      </div>

      <div class="sig-head">
        <div class="sig-title">Staff Member Signature</div>
        <button type="button" class="clear-sig" @click="sigPad?.clear()">🗑 Clear</button>
      </div>
      <SignaturePad ref="sigPad" v-model="form.signature" />
      <div class="sig-foot">
        <div>Date: <span>{{ sigDate }}</span></div>
        <div>Signatory: <span>{{ sigName }}</span></div>
      </div>
    </section>

    <!-- Actions -->
    <div class="actions">
      <button type="button" class="btn secondary" @click="print()">🖨 Print</button>
      <div style="display: flex; gap: 12px">
        <button type="button" class="btn secondary" @click="saveDraft">Save Draft</button>
        <button type="button" class="btn primary" :disabled="store.submitting" @click="submit">
          <span v-if="store.submitting">Saving…</span>
          <span v-else>✓ Submit &amp; Create Profile</span>
        </button>
      </div>
    </div>

    <SuccessModal
      :show="showSuccess"
      title="Profile Created Successfully"
      :message="successMsg"
      @close="onSuccessClose"
      @print="print()"
    />
  </div>
</template>
