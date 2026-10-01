<script setup>
const props = defineProps({ status: { type: String, required: true } })

const map = {
  Draft: 'neutral',
  Pending: 'pending',
  'In Review': 'review',
  Approved: 'approved',
  Rejected: 'rejected',
  Returned: 'pending',
  Completed: 'completed',
  Cancelled: 'neutral',
  Active: 'approved',
  Verified: 'approved',
  Complete: 'completed',
  'Pending Invitation': 'pending',
  Suspended: 'rejected',
  Inactive: 'neutral',
  'Pending First Login': 'pending',
  'Onboarding Incomplete': 'review',
  Expired: 'neutral',
  Revoked: 'rejected',
}
</script>

<template>
  <span class="badge" :class="map[props.status] ?? 'neutral'">{{ props.status }}</span>
</template>
