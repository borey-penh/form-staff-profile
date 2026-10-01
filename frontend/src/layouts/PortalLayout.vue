<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getDashboard } from '@/services/portalService'
import ToastHost from '@/components/ToastHost.vue'
import brandLogo from '@/assets/logo-icon.png'

const icons = {
  home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>',
  shield: '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/>',
  file: '<path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7z"/><path d="M14 3v4h4"/>',
  cap: '<path d="M2 9l10-4 10 4-10 4z"/><path d="M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/><path d="M22 9v5"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
  plane: '<path d="M21 3 3 10.5l6 2.5L21 3z"/><path d="M21 3 11.5 21l-2.5-7.5L21 3z"/>',
  wallet: '<path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2H5"/><circle cx="16" cy="14" r="1"/>',
  folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  chart: '<path d="M4 20v-7"/><path d="M10 20V6"/><path d="M16 20v-10"/><path d="M2 20h20"/>',
  gear: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9l2.1 2.1m10 10 2.1 2.1M19.1 4.9 17 7m-10 10-2.1 2.1"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
  bell: '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
  menu: '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
  chevDown: '<path d="m6 9 6 6 6-6"/>',
  calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
  lock: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
}

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const openMenu = ref(null)

const sidebarOpen = ref(false)
const showNotif = ref(false)
const showUser = ref(false)
const notifItems = ref([])
const approvalItems = ref([])

const sections = computed(() => {
  const base = [
    { label: 'Dashboard', to: '/dashboard', icon: 'home' },
    {
      label: 'Personnel Profile', icon: 'user', children: [
        { label: 'My Profile (5 steps)', to: '/profile' },
      ],
    },
    { label: 'Compliances', icon: 'shield', to: '/compliances' },
    { label: 'Contract History', icon: 'file', to: '/contracts' },
    { label: 'Staff Online Training', icon: 'cap', to: '/training' },
    {
      label: 'Time Management', icon: 'clock', children: [
        { label: 'Leave Application', to: '/leave' },
        { label: 'Overtime Request', to: '/overtime' },
        { label: 'Monthly Timesheet', to: '/timesheet' },
      ],
    },
    {
      label: 'Other Requests', icon: 'plane', children: [
        { label: 'Travel Application', to: '/travel' },
        { label: 'Fuel Log-Sheet', to: '/fuel' },
        { label: 'Purchase Request', to: '/purchase' },
      ],
    },
    { label: 'Finance Management', icon: 'wallet', to: '/vouchers' },
    { label: 'My Requests', icon: 'folder', to: '/requests' },
  ]

  if (auth.isAdmin) {
    base.push(
      { label: 'Admin Dashboard', icon: 'chart', to: '/admin' },
      {
        label: 'Access Management', icon: 'lock', children: [
          { label: 'Roles & Permissions', to: '/access/roles' },
          { label: 'Users', to: '/access/users' },
          { label: 'Profile Change Requests', to: '/access/change-requests' },
        ],
      },
      {
        label: 'Admin Portal', icon: 'gear', children: [
          { label: 'Staff Management', to: '/admin/staff' },
          { label: 'Compliance Manager', to: '/admin/compliances' },
          { label: 'Training Manager', to: '/admin/trainings' },
          { label: 'Finance / Vouchers', to: '/admin/vouchers' },
          { label: 'Holiday Calendar', to: '/admin/holidays' },
          { label: 'Reports', to: '/admin/reports' },
        ],
      }
    )
  }

  // Reviewers (admins + managers) see the queue with a pending badge
  if (auth.can('requests.view-team')) {
    base.push({ label: 'Approval Queue', icon: 'check', to: '/admin/requests' })
  }

  return base
})

const flatPages = computed(() => {
  const map = []
  for (const s of sections.value) {
    if (s.to) map.push({ path: s.to, label: s.label })
    else for (const c of s.children) map.push({ path: c.to, label: c.label })
  }
  return map
})

const pageTitle = computed(() => {
  const exact = flatPages.value.find((p) => p.path === route.path)
  if (exact) return exact.label
  const partial = flatPages.value.find((p) => route.path.startsWith(p.path))
  return partial?.label ?? 'Personnel Portal'
})

const pendingCount = computed(() => approvalItems.value.length + notifItems.value.length)

const initials = computed(() =>
  (auth.user?.firstName?.[0] ?? '') + (auth.user?.lastName?.[0] ?? '')
)

function isActive(item) {
  if (item.to) return route.path === item.to || route.path.startsWith(item.to + '/')
  return item.children.some((c) => route.path === c.to)
}

function toggle(key) {
  openMenu.value = openMenu.value === key ? null : key
}

function closeOverlays() {
  showNotif.value = false
  showUser.value = false
}

function onDocClick(e) {
  if (!e.target.closest('.icon-btn') && !e.target.closest('.userchip')) closeOverlays()
}

function onKey(e) {
  if (e.key === 'Escape') {
    closeOverlays()
    sidebarOpen.value = false
  }
}

watch(() => route.path, () => {
  sidebarOpen.value = false
  closeOverlays()
})

onMounted(async () => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKey)
  try {
    const d = await getDashboard()
    notifItems.value = (d.pendingRequests ?? []).slice(0, 5)
    approvalItems.value = (d.pendingApprovals ?? []).slice(0, 5)
  } catch {
    /* notifications are best-effort */
  }
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onKey)
})

async function doLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="portal" :class="{ 'nav-open': sidebarOpen }">
    <div class="sidebar-backdrop" @click="sidebarOpen = false"></div>

    <aside class="sidebar">
      <div class="brand">
        <span class="brand-logo-wrap"><img class="brand-logo" :src="brandLogo" alt="Live & Learn Cambodia" /></span>
        <span class="brand-text">
          <span class="brand-name">Personnel <span class="accent">Portal</span></span>
          <span class="brand-tag">Human Resources &amp; Staff Care</span>
        </span>
      </div>

      <template v-for="(item, i) in sections" :key="item.label">
        <RouterLink
          v-if="item.to"
          :to="item.to"
          class="nav-link"
          :class="{ active: isActive(item) }"
        >
          <span class="nav-chip"><svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="icons[item.icon]"></svg></span> {{ item.label }}
          <span v-if="item.to === '/admin/requests' && approvalItems.length" class="badge pending" style="margin-left:auto">{{ approvalItems.length }}</span>
        </RouterLink>

        <template v-else>
          <button class="nav-link" :class="{ open: openMenu === i }" @click="toggle(i)">
            <span class="nav-chip"><svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="icons[item.icon]"></svg></span> {{ item.label }}
            <span class="chev" :class="{ open: openMenu === i }"><svg class="nav-ico" style="width:12px;height:12px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" v-html="icons.chevDown"></svg></span>
          </button>
          <div v-show="openMenu === i" class="nav-sub">
            <RouterLink v-for="sub in item.children" :key="sub.to" :to="sub.to">
              {{ sub.label }}
            </RouterLink>
          </div>
        </template>
      </template>

      <div class="sidebar-foot">
        <button class="nav-link" @click="doLogout">
          <span class="nav-chip"><svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="icons.logout"></svg></span> Log out
        </button>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <button class="icon-btn menu-btn" aria-label="Open menu" @click.stop="sidebarOpen = true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" v-html="icons.menu"></svg>
        </button>

        <div class="page-title">{{ pageTitle }}</div>

        <div class="spacer"></div>

        <div class="search topbar-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" v-html="icons.search"></svg>
          <input placeholder="Search here..." />
        </div>

        <div class="icon-btn" :class="{ active: showNotif }" @click.stop="showNotif = !showNotif; showUser = false">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="icons.bell"></svg>
          <span v-if="pendingCount" class="dot"></span>
          <div v-if="showNotif" class="panel" @click.stop>
            <div class="p-head">Notifications</div>

            <template v-if="approvalItems.length">
              <div class="p-item" style="font-weight:700; color:var(--primary); font-size:11px; text-transform:uppercase; letter-spacing:.05em">Awaiting your approval</div>
              <RouterLink v-for="n in approvalItems" :key="'a' + n.id" class="p-item" to="/admin/requests" style="color:inherit; text-decoration:none">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex:none; margin-top:1px; color:var(--amber, #b45309)" v-html="icons.check"></svg>
                <span style="flex:1"><strong>{{ n.type }}</strong> from {{ n.staff }}<br><span class="help">{{ n.status }}</span></span>
              </RouterLink>
            </template>

            <template v-if="notifItems.length">
              <div class="p-item" style="font-weight:700; color:var(--primary); font-size:11px; text-transform:uppercase; letter-spacing:.05em">My requests</div>
              <RouterLink v-for="n in notifItems" :key="n.id" class="p-item" to="/requests" style="color:inherit; text-decoration:none">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex:none; margin-top:1px; color:var(--primary)" v-html="icons.calendar"></svg>
                <span style="flex:1">{{ n.type }} request is <strong>{{ n.status }}</strong></span>
                <span class="when">{{ n.submittedAt ? new Date(n.submittedAt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '' }}</span>
              </RouterLink>
            </template>

            <template v-if="approvalItems.length">
              <RouterLink to="/admin/requests" class="p-foot">Open approval queue</RouterLink>
            </template>
            <template v-else-if="notifItems.length">
              <RouterLink to="/requests" class="p-foot">View all requests</RouterLink>
            </template>
            <div v-if="!approvalItems.length && !notifItems.length" class="p-empty">You're all caught up 🎉</div>
          </div>
        </div>

        <div class="userchip" :class="{ active: showUser }" @click.stop="showUser = !showUser; showNotif = false">
          <div class="avatar">
            <img v-if="auth.user?.photoUrl" :src="auth.user.photoUrl" alt="" />
            <template v-else>{{ initials }}</template>
          </div>
          <div class="meta">
            <div class="name">{{ auth.fullName }}</div>
            <div class="role">{{ auth.isAdmin ? 'Admin / HR' : 'Staff' }}</div>
          </div>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--muted)" v-html="icons.chevDown"></svg>

          <div v-if="showUser" class="panel user-panel" @click.stop>
            <div class="p-head">
              {{ auth.fullName }}
              <span class="u-email">{{ auth.user?.email }}</span>
            </div>
            <RouterLink class="p-item" to="/profile">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="icons.user"></svg>
              My Profile
            </RouterLink>
            <RouterLink class="p-item" to="/requests">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="icons.folder"></svg>
              My Requests
            </RouterLink>
            <button class="p-item p-logout" @click="doLogout">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="icons.logout"></svg>
              Log out
            </button>
          </div>
        </div>
      </header>

      <main class="content">
        <RouterView />
      </main>
    </div>

    <ToastHost />
  </div>
</template>
