import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'

// Session restore happens lazily in the router guard
// (bootstrap() is called there when a token exists).
createApp(App).use(createPinia()).use(router).mount('#app')
