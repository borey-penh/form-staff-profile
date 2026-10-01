<script setup>
import StatusBadge from '@/components/StatusBadge.vue'

const props = defineProps({
  title: { type: String, required: true },
  sub: { type: String, default: '' },
  trail: { type: Array, default: () => [] },
  request: { type: Object, required: true },
})
const emit = defineEmits(['close'])
</script>

<template>
  <div class="modal-overlay show" style="display:flex" @click.self="emit('close')">
    <div class="modal" style="max-width:520px" role="dialog" aria-modal="true">
      <h3>{{ props.title }}</h3>
      <p style="display:flex; align-items:center; gap:8px">
        <StatusBadge :status="props.request.status" />
        <span v-if="props.request.submittedAt">· submitted {{ new Date(props.request.submittedAt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
      </p>

      <div class="card-head" style="padding-bottom:8px; margin-bottom:6px"><h2 style="font-size:13px">Workflow Trail</h2></div>
      <div v-if="props.trail.length" style="max-height:260px; overflow-y:auto">
        <div v-for="(s, i) in props.trail" :key="s.id"
             style="display:flex; gap:10px; padding:7px 0; border-bottom:1px solid #eef2f7; font-size:12.5px">
          <span class="badge info">{{ i + 1 }}</span>
          <div style="flex:1">
            <strong style="text-transform:capitalize">{{ s.action }}</strong>
            <span class="help"> — {{ s.by }}</span>
            <div v-if="s.note" class="help">"{{ s.note }}"</div>
          </div>
          <div class="help" style="white-space:nowrap">{{ s.at ? new Date(s.at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '' }}</div>
        </div>
      </div>
      <div v-else class="help">No actions recorded.</div>

      <div class="row" style="margin-top:16px">
        <button class="btn primary" @click="emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>
