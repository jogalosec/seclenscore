import { createApp }   from 'vue'
import { createPinia } from 'pinia'
import piniaPersistedState from 'pinia-plugin-persistedstate'
import PrimeVue        from 'primevue/config'
import Aura            from '@primevue/themes/aura'
import ToastService    from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import router          from './router'
import App             from './App.vue'

import 'primeicons/primeicons.css'
import 'bootstrap/dist/css/bootstrap.min.css'
import './assets/styles/main.css'

const app   = createApp(App)
const pinia = createPinia()
pinia.use(piniaPersistedState)

app
  .use(pinia)
  .use(router)
  .use(PrimeVue, {
    theme: { preset: Aura, options: { darkModeSelector: '.dark-mode' } }
  })
  .use(ToastService)
  .use(ConfirmationService)
  .mount('#app')
