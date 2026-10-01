<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const emit = defineEmits(['update:modelValue'])
const canvasEl = ref(null)
let ctx = null
let drawing = false

function setupCtx() {
  const canvas = canvasEl.value
  const dpr = window.devicePixelRatio || 1
  canvas.width = canvas.offsetWidth * dpr
  canvas.height = canvas.offsetHeight * dpr
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  ctx.lineWidth = 2.2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = '#0f172a'
}

function pos(e) {
  const r = canvasEl.value.getBoundingClientRect()
  return { x: e.clientX - r.left, y: e.clientY - r.top }
}

function onDown(e) {
  drawing = true
  canvasEl.value.setPointerCapture(e.pointerId)
  const p = pos(e)
  ctx.beginPath()
  ctx.moveTo(p.x, p.y)
}

function onMove(e) {
  if (!drawing) return
  const p = pos(e)
  ctx.lineTo(p.x, p.y)
  ctx.stroke()
}

function onUp() {
  if (!drawing) return
  drawing = false
  emit('update:modelValue', canvasEl.value.toDataURL())
}

function clear() {
  ctx.clearRect(0, 0, canvasEl.value.width, canvasEl.value.height)
  emit('update:modelValue', null)
}

onMounted(() => {
  ctx = canvasEl.value.getContext('2d')
  setupCtx()
})
onBeforeUnmount(() => {})

defineExpose({ clear })
</script>

<template>
  <canvas
    ref="canvasEl"
    class="sig-pad"
    @pointerdown="onDown"
    @pointermove="onMove"
    @pointerup="onUp"
    @pointerleave="onUp"
    @pointercancel="onUp"
  />
</template>
