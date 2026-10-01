<script setup>
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  message: { type: String, default: '' },
})
const emit = defineEmits(['close', 'print'])
</script>

<template>
  <div class="modal-overlay" :class="{ show }">
    <div class="modal">
      <div class="check">✓</div>
      <h3>{{ title }}</h3>
      <p>{{ message }}</p>
      <div class="row">
        <button type="button" class="btn secondary" @click="emit('print')">🖨 Print Form</button>
        <button type="button" class="btn primary" @click="emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>
