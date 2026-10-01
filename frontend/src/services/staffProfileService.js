import apiClient from './apiClient'

export function listStaffProfiles(page = 1) {
  return apiClient.get('/staff-profiles', { params: { page } })
}

export function getStaffProfile(id) {
  return apiClient.get(`/staff-profiles/${id}`)
}

export function createStaffProfile(profile) {
  return apiClient.post('/staff-profiles', profile)
}
