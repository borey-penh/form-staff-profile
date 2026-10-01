<script setup>
const props = defineProps({
  lines: { type: Number, default: 5 },
  variant: { type: String, default: 'lines' }, // lines | card
})
const count = Array.from({ length: props.lines })
</script>

<template>
  <div v-if="props.variant === 'card'" class="grid4" style="margin-bottom:18px">
    <div v-for="(v, i) in 4" :key="i" class="stat-card">
      <div class="skeleton skel-line" style="width:50%"></div>
      <div class="skeleton skel-line" style="width:70%; height:22px; margin-top:10px"></div>
      <div class="skeleton" style="height:7px; border-radius:999px; margin-top:12px"></div>
    </div>
  </div>
  <div v-else class="card">
    <div v-for="(v, i) in count" :key="i" class="skel-row">
      <div class="skeleton skel-line" :style="{ width: 25 + ((i * 13) % 45) + '%' }"></div>
    </div>
  </div>
</template>
