import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastStore = defineStore('toast', () => {
  const message = ref('')
  let timer = null

  function show(msg, ms = 3200) {
    message.value = msg
    clearTimeout(timer)
    timer = setTimeout(() => (message.value = ''), ms)
  }

  return { message, show }
})
