import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from './pages/Dashboard.vue'
import ClientsIndex from './pages/clients/ClientsIndex.vue'
import ClientDetail from './pages/clients/ClientDetail.vue'
import ClientForm from './pages/clients/ClientForm.vue'
import ProjectsIndex from './pages/projects/ProjectsIndex.vue'
import ProjectWorkspace from './pages/projects/ProjectWorkspace.vue'
import ProjectForm from './pages/projects/ProjectForm.vue'
import ProjectWizard from './pages/projects/ProjectWizard.vue'
import ServiceProductsIndex from './pages/service-products/ServiceProductsIndex.vue'
import ServiceProductDetail from './pages/service-products/ServiceProductDetail.vue'

const router = createRouter({
    history: createWebHistory('/admin/client-portal/'),
    routes: [
        { path: '/', name: 'dashboard', component: Dashboard },
        { path: '/clients', name: 'clients.index', component: ClientsIndex },
        { path: '/clients/create', name: 'clients.create', component: ClientForm },
        { path: '/clients/:id', name: 'clients.show', component: ClientDetail, props: true },
        { path: '/clients/:id/edit', name: 'clients.edit', component: ClientForm, props: true },
        { path: '/projects', name: 'projects.index', component: ProjectsIndex },
        { path: '/projects/create', name: 'projects.create', component: ProjectWizard },
        { path: '/projects/:id', name: 'projects.show', component: ProjectWorkspace, props: true },
        { path: '/projects/:id/edit', name: 'projects.edit', component: ProjectForm, props: true },
        { path: '/service-products', name: 'service-products.index', component: ServiceProductsIndex },
        { path: '/service-products/:id', name: 'service-products.show', component: ServiceProductDetail, props: true },
    ],
    scrollBehavior: () => ({ top: 0 }),
})

export default router