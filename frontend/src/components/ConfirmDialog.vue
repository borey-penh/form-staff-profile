<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'

const props = defineProps({
  title: { type: String, default: 'Are you sure?' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'Confirm' },
  danger: { type: Boolean, default: false },
  // Optional note input (replaces window.prompt, which is blocked in sandboxed iframes)
  withNote: { type: Boolean, default: false },
  noteRequired: { type: Boolean, default: false },
  noteLabel: { type: String, default: 'Note' },
  notePlaceholder: { type: String, default: 'Add a note…' },
})
const emit = defineEmits(['confirm', 'cancel'])

const open = ref(true)
const note = ref('')

const canConfirm = computed(() => !props.withNote || !props.noteRequired || note.value.trim().length > 0)

function close() {
  open.value = false
  emit('cancel')
}
function confirm() {
  if (!canConfirm.value) return
  open.value = false
  emit('confirm', props.withNote ? note.value.trim() : undefined)
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
        <div v-if="props.withNote" class="field">
          <label>{{ props.noteLabel }} <span v-if="props.noteRequired" class="req">*</span></label>
          <textarea v-model="note" rows="3" :placeholder="props.notePlaceholder" autofocus></textarea>
          <div v-if="props.noteRequired && !canConfirm" class="help">A note is required.</div>
        </div>
        <div class="row">
          <button class="btn secondary" @click="close">Cancel</button>
          <button
            :class="props.danger ? 'btn danger' : 'btn primary'"
            :disabled="!canConfirm"
            @click="confirm"
          >
            {{ props.confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
