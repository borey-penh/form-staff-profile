<script setup>
import { storeToRefs } from 'pinia'
import { useToastStore } from '@/stores/toast'

const { message } = storeToRefs(useToastStore())
</script>

<template>
  <div class="toast" :class="{ show: message }">{{ message }}</div>
</template>
