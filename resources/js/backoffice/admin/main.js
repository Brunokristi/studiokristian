import { createApp } from 'vue'
import AdminLayout from './layouts/AdminLayout.vue'
import router from './router'

createApp(AdminLayout).use(router).mount('#client-portal-admin')