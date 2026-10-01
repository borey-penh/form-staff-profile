import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '@/services/apiClient'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/invitation/accept/:token',
    name: 'invitation-accept',
    component: () => import('@/views/auth/AcceptInvitationView.vue'),
  },
  {
    path: '/',
    component: () => import('@/layouts/PortalLayout.vue'),
    meta: { auth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },

      // Personnel profile
      { path: 'profile', name: 'profile', component: () => import('@/views/profile/ProfileWizard.vue') },

      // Compliances
      { path: 'compliances', name: 'compliances', component: () => import('@/views/compliance/ComplianceList.vue') },
      { path: 'compliances/:id', name: 'compliance-detail', component: () => import('@/views/compliance/ComplianceDetail.vue') },

      // Contracts
      { path: 'contracts', name: 'contracts', component: () => import('@/views/contract/ContractHistory.vue') },

      // Training
      { path: 'training', name: 'training', component: () => import('@/views/training/TrainingList.vue') },
      { path: 'training/:id', name: 'training-detail', component: () => import('@/views/training/TrainingDetail.vue') },
      { path: 'training/:id/certificate', name: 'training-certificate', component: () => import('@/views/training/TrainingCertificate.vue') },

      // Time management
      { path: 'leave', name: 'leave', component: () => import('@/views/time/LeaveApplication.vue') },
      { path: 'overtime', name: 'overtime', component: () => import('@/views/time/OvertimeRequest.vue') },
      { path: 'timesheet', name: 'timesheet', component: () => import('@/views/time/MonthlyTimesheet.vue') },

      // Other requests
      { path: 'travel', name: 'travel', component: () => import('@/views/requests/TravelApplication.vue') },
      { path: 'fuel', name: 'fuel', component: () => import('@/views/requests/FuelLogsheet.vue') },
      { path: 'purchase', name: 'purchase', component: () => import('@/views/requests/PurchaseRequest.vue') },

      // Finance
      { path: 'vouchers', name: 'vouchers', component: () => import('@/views/finance/VoucherForm.vue') },

      // Shared
      { path: 'requests', name: 'requests', component: () => import('@/views/RequestIndex.vue') },

      // Admin
      { path: 'admin', name: 'admin-dashboard', component: () => import('@/views/admin/AdminDashboard.vue'), meta: { admin: true } },
      { path: 'access/roles', name: 'access-roles', component: () => import('@/views/admin/AdminRoles.vue'), meta: { permission: 'users.manage' } },
      { path: 'access/users', name: 'access-users', component: () => import('@/views/admin/AdminUsers.vue'), meta: { permission: 'users.manage' } },
      { path: 'access/change-requests', name: 'access-change-requests', component: () => import('@/views/admin/AdminChangeRequests.vue'), meta: { permission: 'profile.change-requests.review' } },
      { path: 'admin/staff', name: 'admin-staff', component: () => import('@/views/admin/AdminStaff.vue'), meta: { admin: true } },
      { path: 'admin/staff/:id', name: 'admin-staff-detail', component: () => import('@/views/admin/AdminStaffDetail.vue'), meta: { admin: true } },
      { path: 'admin/compliances', name: 'admin-compliances', component: () => import('@/views/admin/AdminCompliances.vue'), meta: { admin: true } },
      { path: 'admin/trainings', name: 'admin-trainings', component: () => import('@/views/admin/AdminTrainings.vue'), meta: { admin: true } },
      { path: 'admin/requests', name: 'admin-requests', component: () => import('@/views/admin/AdminRequests.vue'), meta: { permission: 'requests.view-team' } },
      { path: 'admin/vouchers', name: 'admin-vouchers', component: () => import('@/views/admin/AdminVouchers.vue'), meta: { admin: true } },
      { path: 'admin/holidays', name: 'admin-holidays', component: () => import('@/views/admin/AdminHolidays.vue'), meta: { admin: true } },
      { path: 'admin/reports', name: 'admin-reports', component: () => import('@/views/admin/AdminReports.vue'), meta: { admin: true } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFound.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.guest && auth.isLoggedIn) return '/dashboard'
  if (!to.meta.auth) return true

  if (!getToken()) return '/login'
  if (!auth.isLoggedIn) {
    await auth.bootstrap()
    if (!auth.isLoggedIn) return '/login'
  }
  if (to.meta.admin && !auth.isAdmin) return '/dashboard'
  if (to.meta.permission && !auth.can(to.meta.permission)) return '/dashboard'
})

export default router
