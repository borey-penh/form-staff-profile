<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'

const props = defineProps({
  title: { type: String, default: 'Are you sure?' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'Confirm' },
  danger: { type: Boolean, default: false },
})
const emit = defineEmits(['confirm', 'cancel'])

const open = ref(true)

function close() {
  open.value = false
  emit('cancel')
}
function confirm() {
  open.value = false
  emit('confirm')
}
function onKey(e) {
  if (e.key === 'Escape') close()
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="modal-overlay show" style="display:flex" @click.self="close">
      <div class="modal" role="dialog" aria-modal="true">
        <h3>{{ props.title }}</h3>
        <p>{{ props.message }}</p>
        <div class="row">
          <button class="btn secondary" @click="close">Cancel</button>
          <button :class="props.danger ? 'btn danger' : 'btn primary'" @click="confirm">
            {{ props.confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
