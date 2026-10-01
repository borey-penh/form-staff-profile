<script setup>
const props = defineProps({
  title: { type: String, default: 'No records found' },
  desc: { type: String, default: '' },
})
</script>

<template>
  <div class="empty-state">
    <div class="ico">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <path d="M10 12h4"/>
      </svg>
    </div>
    <div class="t">{{ props.title }}</div>
    <div v-if="props.desc" class="d">{{ props.desc }}</div>
    <slot />
  </div>
</template>
