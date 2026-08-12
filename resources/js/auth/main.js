import { createApp } from 'vue'
import ClientLogin from './pages/ClientLogin.vue'
import StaffLogin from './pages/StaffLogin.vue'

if (document.querySelector('#staff-login')) {
	createApp(StaffLogin).mount('#staff-login')
}

if (document.querySelector('#client-login')) {
	createApp(ClientLogin).mount('#client-login')
}