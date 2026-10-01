import apiClient from './apiClient'

/* Auth */
export const login = (email, password) => apiClient.post('/auth/login', { email, password })

/* Dashboard */
export const getDashboard = () => apiClient.get('/dashboard')

/* Profile */
export const getProfile = () => apiClient.get('/profile')
export const savePersonal = (d) => apiClient.put('/profile/personal', d)
export const saveQualifications = (items) => apiClient.put('/profile/qualifications', { items })
export const saveFamily = (d) => apiClient.put('/profile/family', d)
export const uploadDocument = (formData) => apiClient.postForm('/profile/documents', formData)
export const deleteDocument = (id) => apiClient.del(`/profile/documents/${id}`)
export const submitDeclaration = (signature) => apiClient.post('/profile/declaration', { signature })
export const submitChangeRequest = (field, requestedValue, reason) =>
  apiClient.post('/profile/change-requests', { field, requestedValue, reason })
export const cancelChangeRequest = (id) => apiClient.del(`/profile/change-requests/${id}`)

/* Compliances */
export const getCompliances = () => apiClient.get('/compliances')
export const signCompliance = (complianceId, signature) => apiClient.post('/compliances/sign', { complianceId, signature })

/* Trainings */
export const getTrainings = () => apiClient.get('/trainings')
export const getTraining = (id) => apiClient.get(`/trainings/${id}`)
export const saveTrainingProgress = (id, progress) => apiClient.post(`/trainings/${id}/progress`, { progress })
export const submitQuiz = (id, answers) => apiClient.post(`/trainings/${id}/quiz`, { answers })

/* Contracts */
export const getContracts = () => apiClient.get('/contracts')

/* Requests */
export const getRequests = (params) => apiClient.get('/requests', { params })
export const getRequest = (id) => apiClient.get(`/requests/${id}`)
export const submitRequest = (type, data) => apiClient.post('/requests', { type, data })
export const actOnRequest = (id, action, note) => apiClient.post(`/requests/${id}/act`, { action, note })

/* Reference */
export const getLeaveBalances = () => apiClient.get('/leave-balances')
export const getHolidays = (year) => apiClient.get('/holidays', { params: { year } })
export const createHoliday = (name, date) => apiClient.post('/admin/holidays', { name, date })
export const deleteHoliday = (id) => apiClient.del(`/admin/holidays/${id}`)
export const getVehicles = () => apiClient.get('/vehicles')

/* Invitations (magic link) */
export const getInvitation = (token) => apiClient.get(`/invitations/${token}`)
export const claimInvitation = (token, password) => apiClient.post(`/invitations/${token}/claim`, { password })

/* Access management */
export const getRoles = () => apiClient.get('/access/roles')
export const getInvitations = () => apiClient.get('/access/invitations')
export const sendInvitation = (d) => apiClient.post('/access/invitations', d)
export const resendInvitation = (id) => apiClient.post(`/access/invitations/${id}/resend`)
export const revokeInvitation = (id) => apiClient.del(`/access/invitations/${id}`)
export const getRole = (id) => apiClient.get(`/access/roles/${id}`)
export const createRole = (d) => apiClient.post('/access/roles', d)
export const updateRole = (id, d) => apiClient.put(`/access/roles/${id}`, d)
export const deleteRole = (id) => apiClient.del(`/access/roles/${id}`)
export const getUsers = (params) => apiClient.get('/access/users', { params })
export const getUser = (id) => apiClient.get(`/access/users/${id}`)
export const updateUser = (id, d) => apiClient.put(`/access/users/${id}`, d)
export const getChangeRequests = (status) => apiClient.get('/access/change-requests', { params: { status } })
export const reviewChangeRequest = (id, action, note) =>
  apiClient.post(`/access/change-requests/${id}/review`, { action, note })

/* Admin */
export const getAdminDashboard = () => apiClient.get('/admin/dashboard')
export const getAdminStaff = (search) => apiClient.get('/admin/staff', { params: { search } })
export const createStaff = (d) => apiClient.post('/admin/staff', d)
export const getAdminStaffDetail = (id) => apiClient.get(`/admin/staff/${id}`)
export const getAdminCompliances = () => apiClient.get('/admin/compliances')
export const createCompliance = (d) => apiClient.post('/admin/compliances', d)
export const updateCompliance = (id, d) => apiClient.put(`/admin/compliances/${id}`, d)
export const deleteCompliance = (id) => apiClient.del(`/admin/compliances/${id}`)
export const getAdminTrainings = () => apiClient.get('/admin/trainings')
export const createTraining = (d) => apiClient.post('/admin/trainings', d)
export const assignTraining = (d) => apiClient.post('/admin/trainings/assign', d)
export const verifyDocument = (id, status) => apiClient.post(`/admin/documents/${id}/verify`, { status })
export const createContract = (d) => apiClient.post('/admin/contracts', d)
export const getAdminVouchers = () => apiClient.get('/admin/vouchers')
export const payVoucher = (id) => apiClient.post(`/admin/vouchers/${id}/pay`)
export const getReports = () => apiClient.get('/admin/reports')
