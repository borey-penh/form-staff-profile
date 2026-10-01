import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { createStaffProfile, listStaffProfiles } from '@/services/staffProfileService'
import { useToastStore } from '@/stores/toast'

export const useStaffProfilesStore = defineStore('staffProfiles', () => {
  /* list state */
  const items = ref([])
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const loading = ref(false)

  /* submission state */
  const submitting = ref(false)
  const lastCreated = ref(null)

  const hasItems = computed(() => items.value.length > 0)

  async function fetch(page = 1) {
    loading.value = true
    try {
      const res = await listStaffProfiles(page)
      items.value = res.data ?? []
      currentPage.value = res.meta?.current_page ?? page
      lastPage.value = res.meta?.last_page ?? 1
      total.value = res.meta?.total ?? items.value.length
    } finally {
      loading.value = false
    }
  }

  async function submit(profile) {
    const toast = useToastStore()
    submitting.value = true
    try {
      const res = await createStaffProfile(profile)
      lastCreated.value = res.data ?? null
      return res
    } catch (err) {
      toast.show(err.message || 'Submission failed.')
      throw err
    } finally {
      submitting.value = false
    }
  }

  return {
    items, currentPage, lastPage, total, loading,
    submitting, lastCreated, hasItems,
    fetch, submit,
  }
})
