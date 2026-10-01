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
export const getVehicles = () => apiClient.get('/vehicles')

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
