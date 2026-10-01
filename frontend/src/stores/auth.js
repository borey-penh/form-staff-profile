import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { setToken, getToken } from '@/services/apiClient'
import apiClient from '@/services/apiClient'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loading = ref(false)

  const isLoggedIn = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const fullName = computed(() => user.value?.fullName ?? '')

  async function bootstrap() {
    if (!getToken()) return
    try {
      user.value = (await apiClient.get('/auth/me')).data ?? (await apiClient.get('/auth/me'))
    } catch {
      user.value = null
    }
  }

  function applyAuth(payload) {
    setToken(payload.token)
    user.value = payload.user
  }

  async function login(email, password) {
    loading.value = true
    try {
      applyAuth(await apiClient.post('/auth/login', { email, password }))
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await apiClient.post('/auth/logout')
    } catch { /* token already invalid */ }
    setToken(null)
    user.value = null
  }

  return { user, loading, isLoggedIn, isAdmin, fullName, bootstrap, login, logout }
})
