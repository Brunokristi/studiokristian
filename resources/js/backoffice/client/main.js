import { createApp } from 'vue'
import ClientApp from './ClientApp.vue'

const payload = JSON.parse(document.querySelector('#client-backoffice-data')?.textContent || '{}')

createApp(ClientApp, { page: payload }).mount('#client-backoffice')
