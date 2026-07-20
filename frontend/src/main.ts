import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin, queryClientOptions } from '@/app/providers/query-client'

import App from './App.vue'
import router from './router'
import { useAuthStore } from '@/stores/auth'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(VueQueryPlugin, queryClientOptions)

const auth = useAuthStore()
auth.initialize()

app.mount('#app')
