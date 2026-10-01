<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getInvitation, claimInvitation } from '@/services/portalService'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const token = String(route.params.token ?? '')

const loading = ref(true)
const error = ref(null)
const expired = ref(false)
const invitation = ref(null)

const wantPassword = ref(false)
const password = ref('')
const password2 = ref('')
const claiming = ref(false)

onMounted(async () => {
  try {
    const res = await getInvitation(token)
    invitation.value = res.invitation
  } catch (e) {
    error.value = e.message
    expired.value = !!e.errors?.expired
  } finally {
    loading.value = false
  }
})

async function join() {
  if (wantPassword.value && password.value !== password2.value) {
    return toast.show('Passwords do not match.')
  }
  if (wantPassword.value && password.value.length < 8) {
    return toast.show('Password must be at least 8 characters.')
  }

  claiming.value = true
  try {
    const res = await claimInvitation(token, wantPassword.value ? password.value : undefined)
    auth.applyAuth(res)
    toast.show(res.requireProfileSetup
      ? '✓ Welcome! Please complete your profile setup.'
      : '✓ Welcome back!')
    router.push(res.requireProfileSetup ? '/profile' : '/dashboard')
  } catch (e) {
    toast.show(e.message)
    if (e.status === 410) {
      error.value = e.message
      loading.value = true // show the error card
    }
  } finally {
    claiming.value = false
  }
}
</script>

<template>
  <div class="invite-wrap">
    <div class="invite-card">
      <div class="invite-brand">
        <div class="ib-title">Personnel <span>Portal</span></div>
        <div class="ib-tag">Human Resources &amp; Staff Care</div>
      </div>

      <LoadingState v-if="loading" />

      <template v-else-if="error">
        <h1>Invitation Unavailable</h1>
        <p class="invite-sub">{{ error }}</p>
        <RouterLink class="btn primary" to="/login" style="text-decoration:none">Go to Login</RouterLink>
      </template>

      <template v-else>
        <h1>Welcome{{ invitation.fullName ? `, ${invitation.fullName}` : '' }} 👋</h1>
        <p class="invite-sub">
          You've been invited to join the <strong>Personnel Portal</strong>.
        </p>

        <div class="invite-meta">
          <div><span>Email</span>{{ invitation.email }}</div>
          <div v-if="invitation.roleLabel"><span>Role</span>{{ invitation.roleLabel }}</div>
          <div v-if="invitation.department"><span>Department</span>{{ invitation.department }}</div>
        </div>

        <template v-if="!wantPassword">
          <button class="btn primary invite-btn" :disabled="claiming" @click="join">
            <span v-if="claiming">Signing you in…</span>
            <span v-else>✓ Accept Invitation &amp; Continue</span>
          </button>
          <button class="btn secondary invite-btn" style="margin-top:10px" @click="wantPassword = true">
            Set a password for future logins
          </button>
        </template>

        <template v-else>
          <div class="field" style="text-align:left">
            <label>Create Password <span class="req">*</span></label>
            <input v-model="password" type="password" placeholder="At least 8 characters">
          </div>
          <div class="field" style="text-align:left">
            <label>Confirm Password <span class="req">*</span></label>
            <input v-model="password2" type="password">
          </div>
          <button class="btn primary invite-btn" :disabled="claiming" @click="join">
            <span v-if="claiming">Creating account…</span>
            <span v-else>Create Account &amp; Continue</span>
          </button>
          <button class="btn secondary invite-btn" style="margin-top:10px" @click="wantPassword = false">Skip</button>
        </template>

        <p class="invite-note">
          <span v-if="invitation.requireProfileSetup">You'll be asked to complete your profile setup after joining.</span>
          <span v-else>You'll land on your dashboard after joining.</span>
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.invite-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(160deg, #f0f7f6, #e8f0ef); padding: 20px; }
.invite-card { background: #fff; border-radius: 18px; box-shadow: 0 18px 50px rgba(15,23,42,.12); padding: 36px 38px; width: 100%; max-width: 440px; text-align: center; }
.invite-brand { margin-bottom: 22px; }
.ib-title { font-size: 20px; font-weight: 800; letter-spacing: -.3px; }
.ib-title span { color: var(--primary, #0e6e66); }
.ib-tag { font-size: 11.5px; color: var(--muted, #64748b); margin-top: 3px; }
h1 { font-size: 19px; margin-bottom: 6px; }
.invite-sub { color: var(--muted, #64748b); font-size: 13.5px; margin-bottom: 20px; }
.invite-meta { background: var(--field-bg, #f6f8fa); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; font-size: 13.5px; }
.invite-meta div { display: flex; justify-content: space-between; padding: 4px 0; gap: 14px; }
.invite-meta span { color: var(--muted, #64748b); }
.invite-btn { width: 100%; }
.invite-note { font-size: 12px; color: var(--muted, #64748b); margin-top: 18px; }
</style>
