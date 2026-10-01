<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, required: true },
  mini: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="segmented" :class="{ mini }">
    <button
      v-for="opt in options"
      :key="opt.value"
      type="button"
      :class="{ active: opt.value === modelValue }"
      @click="emit('update:modelValue', opt.value)"
    >
      {{ opt.label ?? opt.value }}
    </button>
  </div>
</template>
