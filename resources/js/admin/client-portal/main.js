import { createApp } from 'vue'
import ClientPortalAdmin from './ClientPortalAdmin.vue'
import router from './router'

createApp(ClientPortalAdmin).use(router).mount('#client-portal-admin')