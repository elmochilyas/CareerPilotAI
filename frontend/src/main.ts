import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin, queryClientOptions } from '@/app/providers/query-client'

import App from './App.vue'
import router from './router'
import './assets/main.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(VueQueryPlugin, queryClientOptions)

app.mount('#app')
