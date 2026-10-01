<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import loginIllustration from '@/assets/login-illustration.jpg'
import brandLogo from '@/assets/logo-live-learn.png'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    toast.show(`Welcome back, ${auth.fullName} 👋`)
    router.push(auth.isAdmin ? '/admin' : '/dashboard')
  } catch (e) {
    error.value = e.errors?.email?.[0] ?? e.message
  }
}

function signInWithGoogle() {
  toast.show('Google sign-in is not configured on this demo — use email and password instead.')
}
</script>

<template>
  <div class="login-screen">
    <div class="login-card">
      <!-- LEFT: form panel -->
      <div class="login-left">
        <div class="login-brand">
          <img class="login-brand-logo" :src="brandLogo" alt="Live & Learn Cambodia">
        </div>

        <h1 class="login-title">Log in</h1>

        <form @submit.prevent="submit" novalidate>
          <label class="login-label" for="login-email">Email</label>
          <input
            id="login-email"
            v-model="email"
            class="login-input"
            type="email"
            placeholder="name@example.com"
            autocomplete="username"
            aria-label="Email address"
          >

          <label class="login-label" for="login-password">Password</label>
          <div class="login-pass-wrap">
            <input
              id="login-password"
              v-model="password"
              class="login-input"
              :type="showPassword ? 'text' : 'password'"
              placeholder="atleast 8 characters"
              autocomplete="current-password"
              aria-label="Password"
            >
            <button
              type="button"
              class="login-eye"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              @click="showPassword = !showPassword"
            >
              <svg v-if="showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
              <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>

          <div class="login-row-between">
            <span v-if="error" class="login-error">{{ error }}</span>
            <a v-else class="login-forgot" href="#" @click.prevent>Forgot password?</a>
          </div>

          <button class="login-btn-primary" :disabled="auth.loading">
            <span v-if="auth.loading">Logging in…</span>
            <span v-else>Log in</span>
          </button>

          <div class="login-divider">or</div>

          <button type="button" class="login-btn-google" @click="signInWithGoogle">
            <svg viewBox="0 0 48 48" width="18" height="18" aria-hidden="true">
              <path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/>
              <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
              <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
              <path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C36.9 40.4 44 35 44 24c0-1.3-.1-2.6-.4-3.9z"/>
            </svg>
            Sign in with Google
          </button>
        </form>
      </div>

      <!-- RIGHT: full-bleed image panel -->
      <div class="login-right">
        <img
          class="login-illustration"
          :src="loginIllustration"
          alt=""
          aria-hidden="true"
        >
      </div>
    </div>
  </div>
</template>
