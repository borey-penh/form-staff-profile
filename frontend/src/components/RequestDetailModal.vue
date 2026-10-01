<script setup>
import StatusBadge from '@/components/StatusBadge.vue'

const props = defineProps({
  title: { type: String, required: true },
  sub: { type: String, default: '' },
  trail: { type: Array, default: () => [] },
  details: { type: Array, default: () => [] },
  request: { type: Object, required: true },
})
const emit = defineEmits(['close'])
</script>

<template>
  <div class="modal-overlay show" style="display:flex" @click.self="emit('close')">
    <div class="modal" style="max-width:640px" role="dialog" aria-modal="true">
      <h3>{{ props.title }}</h3>
      <p style="display:flex; align-items:center; gap:8px">
        <StatusBadge :status="props.request.status" />
        <span v-if="props.request.submittedAt">· submitted {{ new Date(props.request.submittedAt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
      </p>

      <div class="card-head" style="padding-bottom:8px; margin-bottom:6px"><h2 style="font-size:13px">Request Details</h2></div>
      <div v-if="props.details.length" class="detail-grid">
        <template v-for="d in props.details" :key="d.label">
          <!-- mini table (timesheet entries, purchase items, costs…) -->
          <div v-if="d.kind === 'table'" class="detail-block">
            <div class="help" style="margin-bottom:6px">{{ d.label }}</div>
            <div class="mini-wrap">
              <table class="mini-table">
                <thead>
                  <tr><th v-for="c in d.columns" :key="c">{{ c }}</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in d.rows" :key="i">
                    <td v-for="(cell, j) in row" :key="j" :class="{ num: j > 0 }">{{ cell }}</td>
                  </tr>
                  <tr v-if="!d.rows.length"><td :colspan="d.columns.length" class="help" style="text-align:center; padding:10px">No entries</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- simple label/value row -->
          <div v-else class="detail-row">
            <span class="help" style="min-width:110px">{{ d.label }}</span>
            <div style="flex:1; font-size:13px; white-space:pre-line">{{ d.value }}</div>
          </div>
        </template>
      </div>
      <div v-else class="help">No details recorded for this request.</div>

      <div class="card-head" style="padding-bottom:8px; margin-bottom:6px; margin-top:14px"><h2 style="font-size:13px">Workflow Trail</h2></div>
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

<style scoped>
.detail-grid { display: flex; flex-direction: column; }
.detail-row { display: flex; gap: 12px; padding: 7px 0; border-bottom: 1px solid #eef2f7; align-items: baseline; }
.detail-row:last-child { border-bottom: none; }
.detail-row .help { min-width: 110px; }

.detail-block { padding: 8px 0; border-bottom: 1px solid #eef2f7; }
.detail-block:last-child { border-bottom: none; }
.mini-wrap { max-height: 240px; overflow-y: auto; border: 1px solid var(--border, #e2e8f0); border-radius: 10px; }
.mini-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.mini-table th {
  position: sticky; top: 0; background: var(--field-bg, #f6f8fa); z-index: 1;
  text-align: left; padding: 7px 10px; font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em; color: var(--muted, #64748b);
  border-bottom: 1px solid var(--border, #e2e8f0);
}
.mini-table td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.mini-table tbody tr:last-child td { border-bottom: none; }
.mini-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
</style>
