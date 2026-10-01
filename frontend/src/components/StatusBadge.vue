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
}
</script>

<template>
  <span class="badge" :class="map[props.status] ?? 'neutral'">{{ props.status }}</span>
</template>
